<?php

namespace Tests\Feature\Modules;

use App\Models\AiAnswer;
use App\Models\Transport;
use App\Models\TransportAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsJourneys;
use Tests\TestCase;

/**
 * Local AI of the Transport - Traçabilité module, with the Python service faked.
 */
class TransportAiTest extends TestCase
{
    use BuildsJourneys, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.enabled' => true]);
        $this->buildOliveOilJourney();
    }

    private function fakeService(): void
    {
        Http::fake([
            '*/health' => Http::response(['ready' => true, 'chat' => true]),
            '*/chat' => Http::response(['answer' => 'Le lot a parcouru 270 km au total.', 'model' => 'qwen2.5-1.5b-instruct+nutritrace-lora']),
            '*/transport/analyze' => Http::response([
                'is_anomaly' => true,
                'label' => 'distance_inflated',
                'confidence' => 0.97,
                'reasons' => ['Distance déclarée 900 km pour 120 km à vol d\'oiseau.'],
                'explanation' => 'Ce transport présente une anomalie.',
                'model' => 'random-forest + qwen',
            ]),
        ]);
    }

    private function fakeServiceDown(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
    }

    public function test_a_visitor_asks_the_assistant_and_the_answer_is_cached(): void
    {
        $this->fakeService();
        $token = $this->oilLot->public_token;

        $this->get("/trace/{$token}")->assertOk()->assertSee(__('ai.assistant.title'));

        $this->postJson("/trace/{$token}/assistant", ['question' => 'Quelle distance ce lot a-t-il parcourue ?'])
            ->assertOk()
            ->assertJson(['answer' => 'Le lot a parcouru 270 km au total.', 'cached' => false]);

        // The model got the lot's real data, not just the question.
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/chat')
            && $request['context']['lot']['number'] === $this->oilLot->lot_number
            && count($request['context']['transports']) === 2);

        // Same question, other spelling: served from the database.
        $this->postJson("/trace/{$token}/assistant", ['question' => '  quelle distance ce lot a-t-il parcourue'])
            ->assertOk()
            ->assertJson(['cached' => true]);

        $this->assertSame(1, AiAnswer::count());
        Http::assertSentCount(2); // one health check, one generation
    }

    public function test_a_change_in_the_data_asks_the_model_again(): void
    {
        $this->fakeService();
        $url = "/trace/{$this->oilLot->public_token}/assistant";

        $this->postJson($url, ['question' => 'Quelle distance ?'])->assertJson(['cached' => false]);
        $this->oilLot->transports()->first()->forceFill(['distance_km' => 999])->save();
        $this->postJson($url, ['question' => 'Quelle distance ?'])->assertJson(['cached' => false]);

        $this->assertSame(2, AiAnswer::count());
    }

    public function test_the_assistant_stays_on_the_page_and_says_when_the_service_is_off(): void
    {
        $this->fakeServiceDown();
        $token = $this->oilLot->public_token;

        $this->get("/trace/{$token}")->assertOk()
            ->assertSee(__('ai.assistant.title'))
            ->assertSee(__('ai.assistant.offline'))
            ->assertSee('js-ai-form', false);
        $this->postJson("/trace/{$token}/assistant", ['question' => 'Quelle distance ?'])
            ->assertStatus(503)
            ->assertJson(['message' => __('ai.assistant.unavailable')]);
    }

    public function test_cached_answers_stay_available_offline(): void
    {
        $this->fakeService();
        $token = $this->oilLot->public_token;
        $this->postJson("/trace/{$token}/assistant", ['question' => 'Ce produit est-il local ?'])->assertOk();

        $this->fakeServiceDown();
        cache()->forget('ai.service.up');

        $this->get("/trace/{$token}")->assertOk()->assertSee(__('ai.assistant.offline'));
        $this->postJson("/trace/{$token}/assistant", ['question' => 'Ce produit est-il local ?'])
            ->assertOk()
            ->assertJson(['cached' => true]);
    }

    public function test_questions_are_validated(): void
    {
        $this->fakeService();

        $this->postJson("/trace/{$this->oilLot->public_token}/assistant", ['question' => 'a'])->assertJsonValidationErrors('question');
        $this->postJson("/trace/{$this->oilLot->public_token}/assistant", ['question' => str_repeat('a', 301)])->assertJsonValidationErrors('question');
        $this->postJson('/trace/unknown-token/assistant', ['question' => 'Quelle distance ?'])->assertNotFound();
    }

    public function test_the_shipper_runs_the_anomaly_check_of_a_transport(): void
    {
        $this->fakeService();
        $transport = Transport::where('shipper_id', $this->producer->id)->firstOrFail();
        $page = "/producteur/transports/{$transport->id}";

        $this->actingAs($this->producer)->get($page)->assertOk()->assertSee(__('ai.analysis.run'));

        $this->actingAs($this->producer)->from($page)->post("{$page}/analyse")
            ->assertRedirect($page)
            ->assertSessionHas('success', __('ai.analysis.done'));

        $analysis = TransportAnalysis::firstOrFail();
        $this->assertTrue($analysis->is_anomaly);
        $this->assertSame('distance_inflated', $analysis->label);

        // The service received the transport with its coordinates and dates.
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/transport/analyze')
            && $request['transport']['origin_latitude'] !== null
            && $request['transport']['production_date'] === $this->oliveLot->production_date->toDateString());

        $this->actingAs($this->producer)->get($page)
            ->assertSee(__('ai.labels.distance_inflated'))
            ->assertSee('Ce transport présente une anomalie.')
            ->assertDontSee(__('ai.analysis.run'));

        // A correction makes the analysis out of date.
        $transport->update(['transport_type' => 'van', 'distance_km' => 20]);
        $this->actingAs($this->producer)->get($page)->assertSee(__('ai.analysis.stale'))->assertSee(__('ai.analysis.rerun'));
    }

    public function test_only_people_who_see_the_transport_use_its_ai(): void
    {
        $this->fakeService();
        $transport = Transport::where('shipper_id', $this->producer->id)->firstOrFail();
        $stranger = User::factory()->producer()->create();

        $this->actingAs($stranger)->post("/producteur/transports/{$transport->id}/analyse")->assertForbidden();
        $this->actingAs($stranger)->postJson("/producteur/transports/{$transport->id}/assistant", ['question' => 'Quelle distance ?'])->assertForbidden();

        $this->actingAs($this->transformer)->postJson("/transformateur/transports/{$transport->id}/assistant", ['question' => 'Quelle distance ?'])
            ->assertOk()
            ->assertJson(['answer' => 'Le lot a parcouru 270 km au total.']);
    }

    public function test_the_analysis_card_is_hidden_when_the_service_is_off(): void
    {
        $this->fakeServiceDown();
        $transport = Transport::where('shipper_id', $this->producer->id)->firstOrFail();

        $this->actingAs($this->producer)->get("/producteur/transports/{$transport->id}")
            ->assertOk()
            ->assertDontSee(__('ai.analysis.title'))
            ->assertSee(__('ai.assistant.offline'));

        $this->actingAs($this->producer)->post("/producteur/transports/{$transport->id}/analyse")
            ->assertSessionHas('error', __('ai.analysis.unavailable'));
        $this->assertSame(0, TransportAnalysis::count());
    }

    public function test_the_suggested_questions_can_be_answered_in_advance(): void
    {
        $this->fakeService();

        $this->artisan('ai:warm')->assertSuccessful();

        $this->assertSame(2 * count(__('ai.assistant.examples')), AiAnswer::count());
        $this->postJson("/trace/{$this->oilLot->public_token}/assistant", ['question' => __('ai.assistant.examples')[0]])
            ->assertJson(['cached' => true]);
    }

    public function test_warming_needs_the_service(): void
    {
        $this->fakeServiceDown();

        $this->artisan('ai:warm')->assertFailed();
    }

    public function test_the_export_command_writes_the_training_data(): void
    {
        $folder = storage_path('framework/testing/ai-export');

        $this->artisan('ai:export', ['--output' => $folder])->assertSuccessful();

        $lots = json_decode(File::get($folder.'/lots.json'), true);
        $transports = json_decode(File::get($folder.'/transports.json'), true);
        $this->assertCount(2, $lots);
        $this->assertCount(Transport::count(), $transports);
        $this->assertSame(['lot', 'product', 'journey', 'source_lots', 'transports', 'chain', 'environmental_impact', 'local', 'certifications', 'transparency', 'warnings', 'under_investigation'], array_keys($lots[1]));
        // Public view only: no contact details.
        $this->assertStringNotContainsString($this->producer->email, File::get($folder.'/lots.json'));

        File::deleteDirectory($folder);
    }
}
