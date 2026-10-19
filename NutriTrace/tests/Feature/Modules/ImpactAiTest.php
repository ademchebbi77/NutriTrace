<?php

namespace Tests\Feature\Modules;

use App\Models\ImpactAdvice;
use App\Models\ImpactCheck;
use App\Models\Lot;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsJourneys;
use Tests\TestCase;

/**
 * Local AI of the Impact environnemental - Avis et signalements module, with the Python service faked.
 */
class ImpactAiTest extends TestCase
{
    use BuildsJourneys, RefreshDatabase;

    private Lot $olives;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.enabled' => true]);
        $this->buildOliveOilJourney();
        $this->olives = $this->createOliveLot();
    }

    private function fakeService(string $impactLabel = 'under_declared', string $reviewLabel = 'insult'): void
    {
        Http::fake([
            '*/health' => Http::response(['ready' => true, 'chat' => true, 'impact_check' => true, 'moderation' => true]),
            '*/impact/check' => fn (Request $request) => Http::response([
                'is_anomaly' => $impactLabel !== 'ok', 'label' => $impactLabel, 'confidence' => 0.96, 'risk' => 0.96,
                'findings' => [['indicator' => 'co2_kg', 'label' => $impactLabel, 'confidence' => 0.96, 'reason' => 'Pour le CO₂, 5 kg CO₂e déclarés, moins de la moitié des données tracées.']],
                'reasons' => ['Pour le CO₂, 5 kg CO₂e déclarés, moins de la moitié des données tracées.'],
                'explanation' => $request['explain'] ? 'Attention, une valeur déclarée paraît anormale.' : null, 'model' => 'random-forest',
            ]),
            '*/impact/advice' => Http::response([
                'score' => 72, 'grade' => 'B', 'target' => 'A', 'levers' => ['Réduire l\'énergie de 0,04 kWh par kg à 0,03 kWh par kg suffirait pour passer en note A.'],
                'shares' => [], 'explanation' => 'Voici comment améliorer la note environnementale de ce lot.', 'model' => 'règles de score + qwen',
            ]),
            '*/reviews/moderate' => fn (Request $request) => Http::response([
                'label' => $reviewLabel, 'confidence' => 0.97, 'flagged' => $reviewLabel !== 'ok',
                'reasons' => [$reviewLabel === 'ok' ? 'Avis conforme.' : 'Termes injurieux : escrocs.'],
                'explanation' => $request['explain'] ? 'Cet avis ne peut pas être publié en l\'état.' : null, 'model' => 'tf-idf',
            ]),
        ]);
    }

    private function declaration(array $overrides = []): array
    {
        return ['declared' => ['co2_kg' => ['value' => 5, 'source' => 'MEASURED']]] + $overrides;
    }

    // --- declared figures ----------------------------------------------------------------------

    public function test_the_producer_checks_its_figures_before_saving(): void
    {
        $this->fakeService();
        $page = "/producteur/impacts/{$this->olives->id}";

        $this->actingAs($this->producer)->get($page)->assertOk()
            ->assertSee(__('ai.impact.check'))
            ->assertSee(__('ai.advice.run'));

        $this->actingAs($this->producer)->postJson("{$page}/verifier", $this->declaration())
            ->assertOk()
            ->assertJson(['label' => 'under_declared', 'label_text' => __('ai.impact.labels.under_declared'), 'explanation' => 'Attention, une valeur déclarée paraît anormale.']);

        // The traced figures went with the declaration: they are the floor of an honest declaration.
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/impact/check')
            && $request['declaration']['declared']['co2_kg']['value'] == 5
            && array_key_exists('co2_kg', $request['declaration']['calculated'])
            && $request['declaration']['mass_kg'] == 5000);
        $this->assertNull($this->olives->environmentalImpact->fresh()->declared);

        $this->actingAs($this->producer)->postJson("{$page}/verifier", ['declared' => []])->assertStatus(422);
        $this->actingAs($this->transformer)->postJson("/transformateur/impacts/{$this->olives->id}/verifier", $this->declaration())->assertForbidden();
    }

    public function test_an_anomaly_must_be_confirmed(): void
    {
        $this->fakeService();
        $page = "/producteur/impacts/{$this->olives->id}";

        $this->actingAs($this->producer)->from($page)->put($page, $this->declaration())->assertRedirect($page)->assertSessionHas('ai_check');
        $this->assertNull($this->olives->environmentalImpact->fresh()->declared);
        $this->actingAs($this->producer)->get($page)->assertSee(__('ai.impact.pending_title'))->assertSee(__('ai.impact.confirm'));

        $this->actingAs($this->producer)->put($page, $this->declaration(['ai_confirmed' => 1]))->assertSessionHas('success');
        $this->assertSame(5.0, (float) $this->olives->environmentalImpact->fresh()->declared['co2_kg']['value']);
        $this->assertSame(1, ImpactCheck::count());
    }

    public function test_plausible_figures_and_an_off_service_save_at_once(): void
    {
        $this->fakeService('ok');
        $this->actingAs($this->producer)->put("/producteur/impacts/{$this->olives->id}", $this->declaration())->assertSessionHas('success');
        $this->assertNotNull($this->olives->environmentalImpact->fresh()->declared);

        Http::swap(new Factory);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        cache()->forget('ai.service.up');
        $this->actingAs($this->producer)->put("/producteur/impacts/{$this->olives->id}", ['declared' => ['water_l' => ['value' => 1, 'source' => 'PROVIDED']]])
            ->assertSessionHas('success');
    }

    public function test_the_producer_gets_advice_to_improve_the_grade(): void
    {
        $this->fakeService();
        $page = "/producteur/impacts/{$this->olives->id}";

        $this->actingAs($this->producer)->post("{$page}/conseils")->assertSessionHas('success');

        $advice = ImpactAdvice::firstOrFail();
        $this->assertSame('A', $advice->target_grade);
        // The advisor received the scoring rules of NutriTrace with the lot's figures per kg.
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/impact/advice')
            && $request['lot']['config']['weights']['co2'] == 0.4
            && array_key_exists('energy', $request['lot']['per_kg']));
        $this->actingAs($this->producer)->get($page)
            ->assertSee(__('ai.advice.target', ['grade' => 'A']))
            ->assertSee('suffirait pour passer en note A');
    }

    // --- reviews -------------------------------------------------------------------------------

    public function test_a_flagged_review_waits_for_the_admin(): void
    {
        $this->fakeService();
        $consumer = User::factory()->create();
        $trace = "/trace/{$this->oilLot->public_token}";

        $this->actingAs($consumer)->post("{$trace}/avis", ['rating' => 1, 'comment' => 'Bande d\'escrocs !'])
            ->assertSessionHas('warning', __('ai.moderation.held'));

        $review = Review::latest('id')->firstOrFail();
        $this->assertSame(Review::PENDING, $review->moderation_status);
        $this->assertSame('insult', $review->moderation_label);
        // Another visitor does not see it; its author sees that it is waiting.
        $visitor = User::factory()->create();
        $this->actingAs($visitor)->get($trace)->assertDontSee('Bande d&#039;escrocs', false);
        $this->actingAs($consumer)->get($trace)->assertSee(__('ai.moderation.own_pending'));
        $this->actingAs($consumer)->get('/consommateur/avis')->assertSee(__('ai.moderation.statuses.pending'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/moderation-avis')->assertOk()
            ->assertSee('Bande d&#039;escrocs', false)
            ->assertSee(__('ai.moderation.labels.insult'))
            ->assertSee(__('ai.moderation.explain'));

        $this->actingAs($admin)->post("/admin/moderation-avis/{$review->id}/expliquer")->assertSessionHas('success');
        $this->assertSame('Cet avis ne peut pas être publié en l\'état.', $review->fresh()->moderation_explanation);

        $this->actingAs($admin)->post("/admin/moderation-avis/{$review->id}/refuser")->assertSessionHas('success');
        $this->assertSame(Review::REJECTED, $review->fresh()->moderation_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.rejected']);
        $this->actingAs($visitor)->get($trace)->assertDontSee('Bande d&#039;escrocs', false);

        $this->actingAs($admin)->post("/admin/moderation-avis/{$review->id}/publier");
        $this->actingAs($visitor)->get($trace)->assertSee('Bande d&#039;escrocs', false);
        $this->actingAs($consumer)->get('/admin/moderation-avis')->assertForbidden();
    }

    public function test_a_polite_review_and_any_review_without_the_service_are_published(): void
    {
        $this->fakeService('ok', 'ok');
        $consumer = User::factory()->create();

        $this->actingAs($consumer)->post("/trace/{$this->oilLot->public_token}/avis", ['rating' => 2, 'comment' => 'Décevant, trop cher.'])
            ->assertSessionHas('success', __('reviews.saved'));
        $this->assertSame(Review::PUBLISHED, Review::latest('id')->first()->moderation_status);

        Http::swap(new Factory);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        cache()->forget('ai.service.up');
        $other = User::factory()->create();
        $this->actingAs($other)->post("/trace/{$this->oilLot->public_token}/avis", ['rating' => 1, 'comment' => 'Bande d\'escrocs !']);
        $this->assertSame(Review::PUBLISHED, Review::latest('id')->first()->moderation_status);
    }

    public function test_the_export_includes_declarations_lots_and_reviews(): void
    {
        $folder = storage_path('framework/testing/ai-export-impact');

        $this->artisan('ai:export', ['--output' => $folder])->assertSuccessful();

        $advice = json_decode(File::get($folder.'/advice.json'), true);
        $this->assertSame(Lot::has('environmentalImpact')->count(), count($advice));
        $this->assertSame(['thresholds', 'weights', 'grades', 'factors'], array_keys($advice[0]['config']));
        $this->assertFileExists($folder.'/declarations.json');
        $this->assertFileExists($folder.'/reviews.json');

        File::deleteDirectory($folder);
    }
}
