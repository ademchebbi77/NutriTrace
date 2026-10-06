<?php

namespace Tests\Feature\Modules;

use App\Enums\CertificationStatus;
use App\Enums\DataSource;
use App\Enums\ProductionMethod;
use App\Enums\ReportStatus;
use App\Models\Certification;
use App\Models\Report;
use App\Models\User;
use App\Services\CertificationService;
use App\Services\Scoring\FootprintCalculator;
use App\Services\Scoring\GreenwashingWarnings;
use App\Services\Scoring\LocalRule;
use App\Services\Scoring\TrustScoreCalculator;
use App\Services\Traceability\JourneyBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsJourneys;
use Tests\TestCase;

class ScoringTest extends TestCase
{
    use BuildsJourneys, RefreshDatabase;

    public function test_the_footprint_sums_every_stage_back_to_the_farm(): void
    {
        $this->buildOliveOilJourney();

        $impact = $this->oilLot->environmentalImpact;
        $toMill = $this->oliveLot->transports->first();

        // Electricity: 0.47 kg CO2e/kWh. Truck: 0.105 kg CO2e per tonne-km.
        $production = 180 * 0.47;
        $transformation = 450 * 0.47;
        $transport = $toMill->distance_km * 5 * 0.105 + 270 * 0.9 * 0.105;

        $this->assertEqualsWithDelta($production + $transformation + $transport, $impact->co2_kg, 0.01);
        $this->assertEqualsWithDelta($transport, $impact->transport_co2_kg, 0.01);
        $this->assertEqualsWithDelta(62500, $impact->water_l, 0.01);
        $this->assertEqualsWithDelta(630, $impact->energy_kwh, 0.01);
        $this->assertEqualsWithDelta($toMill->distance_km + 270, $impact->food_miles_km, 0.01);

        $this->assertSame(DataSource::CALCULATED, $impact->co2_kg_source);
        $this->assertSame(DataSource::CALCULATED, $impact->transport_co2_kg_source);
        $this->assertSame(DataSource::PROVIDED, $impact->water_l_source);
        $this->assertNull($impact->waste_kg);

        $this->assertGreaterThan(40, $impact->score);
        $this->assertLessThan(90, $impact->score);
        $this->assertSame(app(FootprintCalculator::class)->grade($impact->score), $impact->grade);
    }

    public function test_a_partial_use_of_a_source_lot_inherits_a_share_of_its_footprint(): void
    {
        $this->createActors();
        $olives = $this->createOliveLot();
        $this->transferToTransformer($olives);

        // Only 40 % of the olives go into this oil.
        $oil = $this->transform($olives->fresh(), used: 2000, output: 360)->outputLot->fresh();
        $stages = app(FootprintCalculator::class)->stages($oil);

        $this->assertEqualsWithDelta(180 * 0.47 * 0.4, $stages['production_co2'], 0.01);
        $this->assertEqualsWithDelta(60000 * 0.4 + 2500, $stages['water'], 0.01);
        $this->assertSame(3000.0, $olives->fresh()->quantity);
    }

    public function test_declared_values_replace_calculated_ones_and_keep_their_source(): void
    {
        $this->buildOliveOilJourney();

        $impact = app(FootprintCalculator::class)->declare($this->oilLot, $this->transformer, [
            'co2_kg' => ['value' => 250, 'source' => 'MEASURED'],
            'waste_kg' => ['value' => 40, 'source' => 'PROVIDED'],
        ]);

        $this->assertSame(250.0, $impact->co2_kg);
        $this->assertSame(DataSource::MEASURED, $impact->co2_kg_source);
        $this->assertSame(40.0, $impact->waste_kg);
        $this->assertSame(DataSource::PROVIDED, $impact->waste_kg_source);
        $this->assertSame(DataSource::PROVIDED, $impact->water_l_source);
    }

    public function test_emission_factors_come_from_the_configuration(): void
    {
        $this->buildOliveOilJourney();
        $before = $this->oilLot->environmentalImpact->transport_co2_kg;

        config(['footprint.emission_factors.transport.truck' => 0.21]);
        $after = app(FootprintCalculator::class)->refresh($this->oilLot->fresh())->transport_co2_kg;

        $this->assertEqualsWithDelta($before * 2, $after, 0.01);
    }

    public function test_grades_follow_the_configured_limits(): void
    {
        $calculator = app(FootprintCalculator::class);

        $this->assertSame('A', $calculator->grade(80));
        $this->assertSame('B', $calculator->grade(79));
        $this->assertSame('D', $calculator->grade(20));
        $this->assertSame('E', $calculator->grade(19));
        $this->assertNull($calculator->grade(null));
    }

    public function test_the_trust_score_explains_itself(): void
    {
        $this->buildOliveOilJourney();

        $score = app(TrustScoreCalculator::class)->calculate($this->oilLot);
        $components = collect($score->components)->keyBy('key');

        $this->assertSame(25.0, $components['chain_completeness']['points']);
        $this->assertSame(20.0, $components['verified_actors']['points']);
        $this->assertSame(0.0, $components['certifications']['points']);
        $this->assertSame(15.0, $components['chain_integrity']['points']);
        // CO2, water, energy and food miles are known; waste and packaging are not.
        $this->assertEqualsWithDelta(20 * 4 / 6, $components['environmental_data']['points'], 0.1);
        $this->assertSame([], $score->penalties);
        $this->assertSame($score->score, $this->oilLot->fresh()->trust_score);
        $this->assertNotEmpty($components['chain_completeness']['detail']);
    }

    public function test_valid_certifications_with_proof_raise_the_score(): void
    {
        $this->buildOliveOilJourney();
        $before = $this->oilLot->trust_score;

        $certification = Certification::factory()->for($this->oilLot, 'certifiable')->create(['owner_id' => $this->transformer->id]);
        $this->assertSame($before, $this->oilLot->fresh()->trust_score);

        app(CertificationService::class)->approve($certification, User::factory()->admin()->create());

        $this->assertSame($before + 20, $this->oilLot->fresh()->trust_score);
    }

    public function test_unverified_actors_reports_and_bad_certificates_lower_the_score(): void
    {
        $this->buildOliveOilJourney();
        $before = $this->oilLot->trust_score;

        $this->distributor->organization->update(['is_verified' => false]);
        $this->distributor->organization->forceFill(['is_verified' => false])->save();
        Certification::factory()->rejected()->for($this->oilLot->product, 'certifiable')->create();
        $report = Report::factory()->for($this->oilLot, 'reportable')->create();

        $score = app(TrustScoreCalculator::class)->calculate($this->oilLot->fresh());
        $penalties = collect($score->penalties)->keyBy('key');

        $this->assertEqualsWithDelta(20 * 2 / 3, collect($score->components)->firstWhere('key', 'verified_actors')['points'], 0.1);
        $this->assertSame(10.0, $penalties['rejected_certification']['points']);
        $this->assertSame(5.0, $penalties['open_report']['points']);
        $this->assertLessThan($before, $this->oilLot->fresh()->trust_score);

        // Closing the report lifts its penalty.
        $withReport = $this->oilLot->fresh()->trust_score;
        $report->forceFill(['status' => ReportStatus::RESOLVED])->save();
        $this->assertSame($withReport + 5, $this->oilLot->fresh()->trust_score);
    }

    public function test_a_broken_chain_costs_the_integrity_points(): void
    {
        $this->buildOliveOilJourney();

        DB::table('traceability_events')->where('lot_id', $this->oliveLot->id)->limit(1)->update(['location' => 'Ailleurs']);

        $score = app(TrustScoreCalculator::class)->calculate($this->oilLot->fresh());

        $this->assertSame(0.0, collect($score->components)->firstWhere('key', 'chain_integrity')['points']);
        $this->assertContains('chain_broken', $this->warningCodes($this->oilLot));
    }

    public function test_an_organic_claim_needs_a_verified_bio_certificate(): void
    {
        $this->createActors();
        $lot = $this->createOliveLot();
        $lot->production->update(['production_method' => ProductionMethod::ORGANIC]);

        $this->assertContains('bio_unverified', $this->warningCodes($lot));

        // A pending certificate is not enough.
        $certification = Certification::factory()->bio()->for($lot->product, 'certifiable')->create();
        $codes = $this->warningCodes($lot);
        $this->assertContains('bio_unverified', $codes);
        $this->assertContains('certificate_pending', $codes);

        $certification->forceFill(['status' => CertificationStatus::VERIFIED])->save();
        $this->assertNotContains('bio_unverified', $this->warningCodes($lot));
    }

    public function test_expired_and_rejected_certificates_raise_warnings(): void
    {
        $this->createActors();
        $lot = $this->createOliveLot();

        Certification::factory()->verified()->for($lot, 'certifiable')->create(['expiration_date' => now()->subMonth()]);
        Certification::factory()->rejected()->for($lot, 'certifiable')->create();

        $warnings = app(GreenwashingWarnings::class)->for($lot->fresh());

        $this->assertContains('certificate_expired', $warnings->pluck('code'));
        $this->assertContains('certificate_rejected', $warnings->pluck('code'));
        // Most serious first.
        $this->assertSame('high', $warnings->first()['severity']);
        $this->assertNotEmpty($warnings->first()['message']);
    }

    public function test_a_local_claim_is_checked_against_the_distance_travelled(): void
    {
        $this->buildOliveOilJourney();

        $this->assertNotContains('local_claim_far', $this->warningCodes($this->oilLot));

        Certification::factory()->local()->for($this->oilLot, 'certifiable')->create();

        // Sfax -> Tunis: about 235 km as the crow flies, 270 km by road.
        $this->assertContains('local_claim_far', $this->warningCodes($this->oilLot));
    }

    public function test_local_is_measured_from_the_farm_to_the_final_destination(): void
    {
        $this->buildOliveOilJourney();
        $rule = app(LocalRule::class);

        $result = $rule->evaluate(app(JourneyBuilder::class)->build($this->oilLot));
        $this->assertFalse($result['is_local']);
        $this->assertEqualsWithDelta(235, $result['distance_km'], 10);

        config(['trust.local_radius_km' => 300]);
        $this->assertTrue($rule->evaluate(app(JourneyBuilder::class)->build($this->oilLot->fresh()))['is_local']);

        // Unknown while the lot has not arrived anywhere.
        $fresh = $this->createOliveLot();
        $this->assertNull($rule->evaluate(app(JourneyBuilder::class)->build($fresh))['is_local']);
    }

    public function test_missing_environmental_data_and_inconsistent_dates_are_flagged(): void
    {
        $this->createActors();

        $lot = $this->createOliveLot();
        $lot->production->update(['resources_used' => null, 'latitude' => null, 'longitude' => null]);

        $codes = $this->warningCodes($lot);
        $this->assertContains('environmental_data_missing', $codes);
        $this->assertContains('origin_not_geolocated', $codes);

        // Shipped before it was harvested.
        $early = $this->createOliveLot(date: '2025-11-20');
        $this->transferToTransformer($early, departure: '2025-11-10 08:00', arrival: '2025-11-09 08:00');

        $codes = $this->warningCodes($early);
        $this->assertContains('transport_before_production', $codes);
        $this->assertContains('arrival_before_departure', $codes);
    }

    public function test_the_expiry_command_expires_certificates_and_recomputes_scores(): void
    {
        $this->buildOliveOilJourney();

        $certification = Certification::factory()->verified()->for($this->oilLot, 'certifiable')->create(['expiration_date' => now()->addDay()]);
        $still = Certification::factory()->verified()->for($this->oliveLot, 'certifiable')->create(['expiration_date' => null]);
        $valid = $this->oilLot->fresh()->trust_score;

        $this->artisan('certifications:expire')->assertSuccessful();
        $this->assertSame(CertificationStatus::VERIFIED, $certification->fresh()->status);

        $this->travel(2)->days();
        $this->artisan('certifications:expire')->assertSuccessful();

        $this->assertSame(CertificationStatus::EXPIRED, $certification->fresh()->status);
        $this->assertSame(CertificationStatus::VERIFIED, $still->fresh()->status);
        // 20 points of certification lost, 5 points of penalty.
        $this->assertSame($valid - 25, $this->oilLot->fresh()->trust_score);
        $this->assertContains('certificate_expired', $this->warningCodes($this->oilLot));
    }

    /**
     * @return list<string>
     */
    private function warningCodes($lot): array
    {
        return app(GreenwashingWarnings::class)->for($lot->fresh())->pluck('code')->all();
    }
}
