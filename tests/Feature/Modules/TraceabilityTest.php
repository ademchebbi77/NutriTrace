<?php

namespace Tests\Feature\Modules;

use App\Enums\EventType;
use App\Enums\LotStatus;
use App\Models\TraceabilityEvent;
use App\Services\SaleService;
use App\Services\Traceability\ChainVerifier;
use App\Services\Traceability\JourneyBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\BuildsJourneys;
use Tests\TestCase;

class TraceabilityTest extends TestCase
{
    use BuildsJourneys, RefreshDatabase;

    public function test_observers_create_the_matching_events(): void
    {
        $this->buildOliveOilJourney();

        $types = fn ($lot) => $lot->events()->orderBy('id')->pluck('event_type')->map->value->all();

        // Olives: produced, shipped to the mill, arrived, consumed by the transformation.
        $this->assertSame(['PRODUCTION', 'TRANSPORT', 'TRANSPORT', 'TRANSFORMATION'], $types($this->oliveLot));

        // Oil: made, shipped, sent to the distributor, arrived, received, put in store.
        $this->assertSame(
            ['TRANSFORMATION', 'TRANSPORT', 'DISTRIBUTION', 'TRANSPORT', 'DISTRIBUTION', 'DISTRIBUTION'],
            $types($this->oilLot),
        );

        $production = $this->oliveLot->events()->where('event_type', EventType::PRODUCTION)->first();

        $this->assertTrue($production->source->is($this->oliveLot->production));
        $this->assertTrue($production->actor->is($this->producer));
        $this->assertSame('2025-11-20', $production->occurred_at->toDateString());
        $this->assertEqualsWithDelta(34.7406, $production->latitude, 0.0001);
    }

    public function test_the_journey_of_a_transformed_lot_goes_back_to_the_farm(): void
    {
        $this->buildOliveOilJourney();

        $journey = app(JourneyBuilder::class)->build($this->oilLot);

        $this->assertTrue($journey->isTransformed());
        $this->assertSame([$this->oliveLot->id, $this->oilLot->id], $journey->lots()->pluck('id')->all());
        $this->assertSame(5000.0, $journey->sources[0]['quantity_used']);
        $this->assertSame('Sfax', $journey->productions()->first()->location_city);

        $events = $journey->allEvents();

        $this->assertCount(10, $events);
        $this->assertSame(EventType::PRODUCTION, $events->first()->event_type);
        $this->assertSame(EventType::DISTRIBUTION, $events->last()->event_type);
        // Chronological from the grove to the shelf.
        $timestamps = $events->map(fn ($event) => $event->occurred_at->timestamp);
        $this->assertSame($timestamps->sort()->values()->all(), $timestamps->all());

        $organizations = $journey->organizations()->pluck('id');
        $this->assertCount(3, $organizations);

        // The map goes from Sfax to Tunis.
        $points = $journey->points();
        $this->assertEqualsWithDelta(34.74, $points[0]['lat'], 0.01);
        $this->assertEqualsWithDelta(36.81, end($points)['lat'], 0.01);
    }

    public function test_a_farm_lot_has_a_simple_journey(): void
    {
        $this->createActors();
        $lot = $this->createOliveLot();

        $journey = app(JourneyBuilder::class)->build($lot);

        $this->assertFalse($journey->isTransformed());
        $this->assertCount(1, $journey->allEvents());
        $this->assertCount(1, $journey->lots());
    }

    public function test_the_hash_chain_is_valid_and_detects_tampering(): void
    {
        $this->buildOliveOilJourney();

        $verifier = app(ChainVerifier::class);

        $status = $verifier->verify($this->oilLot);
        $this->assertTrue($status->valid);
        $this->assertSame(6, $status->events);

        $events = $this->oilLot->events()->orderBy('id')->get();
        $this->assertSame(TraceabilityEvent::GENESIS_HASH, $events[0]->previous_hash);
        $this->assertSame($events[0]->hash, $events[1]->previous_hash);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $events[0]->hash);

        // Someone rewrites history directly in the database.
        DB::table('traceability_events')->where('id', $events[1]->id)->update(['description' => 'Transport local de 5 km']);

        $tampered = $verifier->verify($this->oilLot);
        $this->assertFalse($tampered->valid);
        $this->assertSame($events[1]->id, $tampered->brokenEventId);

        // The upstream chain is still intact, but the whole journey is not.
        $this->assertTrue($verifier->verify($this->oliveLot)->valid);
        $this->assertFalse($verifier->verifyJourney(app(JourneyBuilder::class)->build($this->oilLot))->valid);
    }

    public function test_deleting_an_event_from_the_middle_breaks_the_chain(): void
    {
        $this->buildOliveOilJourney();

        $middle = $this->oilLot->events()->orderBy('id')->skip(2)->first();
        DB::table('traceability_events')->where('id', $middle->id)->delete();

        $this->assertFalse(app(ChainVerifier::class)->verify($this->oilLot)->valid);
    }

    public function test_events_are_append_only(): void
    {
        $this->createActors();
        $event = $this->createOliveLot()->events()->first();

        try {
            $event->update(['description' => 'Autre chose']);
            $this->fail('Updating an event should not be possible.');
        } catch (LogicException) {
            $this->assertNotSame('Autre chose', $event->fresh()->description);
        }

        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_correcting_a_production_adds_an_event_instead_of_rewriting(): void
    {
        $this->createActors();
        $lot = $this->createOliveLot();

        $lot->production->update(['quantity' => 4800]);

        $this->assertSame(2, $lot->events()->count());
        $this->assertSame(4800.0, $lot->fresh()->quantity);
        $this->assertTrue(app(ChainVerifier::class)->verify($lot)->valid);
    }

    public function test_sales_are_recorded_and_empty_the_lot(): void
    {
        $this->buildOliveOilJourney();

        $sales = app(SaleService::class);

        $sales->record($this->distribution, 300);
        $this->assertSame(600.0, $this->oilLot->fresh()->quantity);
        $this->assertSame(LotStatus::IN_STORE, $this->oilLot->fresh()->status);

        $event = $sales->record($this->distribution, 600);
        $this->assertSame(EventType::SALE, $event->event_type);
        $this->assertSame(600, $event->metadata['quantity']);
        $this->assertSame(LotStatus::SOLD_OUT, $this->oilLot->fresh()->status);
        $this->assertTrue(app(ChainVerifier::class)->verify($this->oilLot)->valid);
    }
}
