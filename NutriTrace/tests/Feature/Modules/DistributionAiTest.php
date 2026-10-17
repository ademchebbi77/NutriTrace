<?php

namespace Tests\Feature\Modules;

use App\Models\Certification;
use App\Models\CertificationAnalysis;
use App\Models\Distribution;
use App\Models\DistributorAiAnswer;
use App\Models\Product;
use App\Models\ReceptionCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsJourneys;
use Tests\TestCase;

/**
 * Local AI of the Distribution - Certification module, with the Python service faked.
 */
class DistributionAiTest extends TestCase
{
    use BuildsJourneys, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.enabled' => true]);
        Storage::fake('local');
        $this->buildOliveOilJourney();
    }

    private function fakeService(string $level = 'moyen', bool $training = false): void
    {
        $risk = fn (array $certificate) => [
            'id' => $certificate['id'], 'level' => $level, 'risk' => $level === 'faible' ? 0.05 : 0.55, 'confidence' => 0.9,
            'reasons' => ['Le numéro « TN-BIO-001 » ne suit pas le format des certificats de Ecocert.'],
            'document_readable' => true, 'explanation' => null, 'model' => 'random-forest',
        ];

        Http::fake([
            '*/health' => Http::response(['ready' => true, 'chat' => ! $training, 'anomaly' => true, 'certification' => true, 'reception' => true, 'training' => $training]),
            // While training, the service has no LLM and refuses to chat.
            '*/distribution/chat' => $training
                ? Http::response(['detail' => 'training'], 503)
                : Http::response(['answer' => 'Le lot LOT-X expire dans 3 jours.', 'model' => 'qwen+distribution-lora']),
            '*/distribution/reception' => Http::response([
                'decision' => 'reserves', 'confidence' => 0.97, 'reasons' => ['Date limite proche : 5 jours restants.'],
                'explanation' => 'Vous pouvez accepter ce lot, avec quelques précautions.', 'transport_label' => 'normal', 'model' => 'random-forest + qwen',
            ]),
            '*/certification/risk/batch' => fn (Request $request) => Http::response(['results' => array_map($risk, $request['certificates'])]),
            '*/certification/risk' => fn (Request $request) => Http::response(
                ['explanation' => $request['explain'] ? 'Ce certificat présente quelques points à vérifier.' : null] + $risk($request['certificate'])
            ),
        ]);
    }

    private function fakeServiceDown(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
    }

    private function pendingDistribution(): Distribution
    {
        $lot = $this->createOliveLot();
        $this->actingAs($this->producer)->post("/producteur/lots/{$lot->id}/transferer", [
            'recipient_id' => $this->distributor->id, 'transport_type' => 'truck', 'departure_date' => '2025-11-21 08:00',
        ])->assertSessionHasNoErrors();

        return Distribution::where('lot_id', $lot->id)->firstOrFail();
    }

    // --- distributor assistant ---------------------------------------------------------------

    public function test_the_distributor_asks_about_its_own_stock(): void
    {
        $this->fakeService();
        $other = Distribution::factory()->create(['distributor_id' => User::factory()->distributor()->create()->id]);
        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$this->distribution->id}/ventes", ['quantity' => 12])->assertSessionHasNoErrors();

        $this->actingAs($this->distributor)->get('/distributeur/assistant')
            ->assertOk()
            ->assertSee(__('ai.distributor.title'))
            ->assertSee(__('ai.distributor.examples')[0])
            ->assertDontSee(__('ai.distributor.offline'));

        $this->actingAs($this->distributor)->postJson('/distributeur/assistant', ['question' => 'Quels lots expirent cette semaine ?'])
            ->assertOk()
            ->assertJson(['answer' => 'Le lot LOT-X expire dans 3 jours.', 'cached' => false]);

        // Only this distributor's data was sent, with its sales.
        Http::assertSent(function (Request $request) use ($other) {
            if (! str_ends_with($request->url(), '/distribution/chat')) {
                return false;
            }
            $lots = array_column($request['context']['distributions'], 'lot');

            return in_array($this->oilLot->lot_number, $lots, true)
                && ! in_array($other->lot->lot_number, $lots, true)
                && $request['context']['sales'] !== []
                && $request['context']['today'] === today()->toDateString();
        });

        $this->actingAs($this->distributor)->postJson('/distributeur/assistant', ['question' => 'quels lots expirent cette semaine'])
            ->assertJson(['cached' => true]);
        $this->assertSame(1, DistributorAiAnswer::count());

        $this->actingAs($this->producer)->get('/distributeur/assistant')->assertForbidden();
    }

    public function test_the_assistant_says_when_the_model_is_training(): void
    {
        $this->fakeService(training: true);

        $this->actingAs($this->distributor)->get('/distributeur/assistant')->assertOk()->assertSee(__('ai.distributor.training'));
        $this->actingAs($this->distributor)->postJson('/distributeur/assistant', ['question' => 'Quel lot se vend le moins ?'])
            ->assertStatus(503)
            ->assertJson(['message' => __('ai.assistant.training')]);
    }

    public function test_the_assistant_page_works_without_the_service(): void
    {
        $this->fakeServiceDown();

        $this->actingAs($this->distributor)->get('/distributeur/assistant')->assertOk()->assertSee(__('ai.distributor.offline'));
        $this->actingAs($this->distributor)->postJson('/distributeur/assistant', ['question' => 'Quel lot se vend le moins ?'])->assertStatus(503);
    }

    // --- reception check -----------------------------------------------------------------------

    public function test_the_distributor_checks_a_lot_before_accepting_it(): void
    {
        $this->fakeService();
        $distribution = $this->pendingDistribution();
        $page = "/distributeur/distributions/{$distribution->id}";

        $this->actingAs($this->distributor)->get('/distributeur/receptions')->assertOk()->assertSee(__('ai.reception.check'));
        $this->actingAs($this->distributor)->get($page)->assertOk()->assertSee(__('ai.reception.run'));

        $this->actingAs($this->distributor)->post("{$page}/verifier")->assertRedirect($page)->assertSessionHas('success');

        $check = ReceptionCheck::firstOrFail();
        $this->assertSame('reserves', $check->decision);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/distribution/reception')
            && $request['reception']['lot']['number'] === $distribution->lot->lot_number
            && $request['reception']['transport']['origin_latitude'] !== null
            && array_key_exists('chain_valid', $request['reception']));

        $this->actingAs($this->distributor)->get($page)
            ->assertSee(__('ai.reception.decisions.reserves'))
            ->assertSee('Vous pouvez accepter ce lot, avec quelques précautions.');

        // Only the receiver, and only before deciding.
        $this->actingAs(User::factory()->distributor()->create())->post("/distributeur/distributions/{$distribution->id}/verifier")->assertForbidden();
        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$distribution->id}/recevoir");
        $this->actingAs($this->distributor)->get($page)->assertDontSee(__('ai.reception.title'));
        $this->actingAs($this->distributor)->post("{$page}/verifier")->assertForbidden();
    }

    // --- certificate risk ----------------------------------------------------------------------

    public function test_a_suspicious_certificate_warns_the_producer_and_the_admin(): void
    {
        $this->fakeService('moyen');
        $product = Product::factory()->create(['created_by' => $this->producer->id]);

        $response = $this->actingAs($this->producer)->post('/producteur/certifications', [
            'target' => "product:{$product->id}", 'name' => 'Agriculture biologique', 'type' => 'BIO', 'issuing_organization' => 'Ecocert',
            'certificate_number' => 'TN-BIO-001', 'issue_date' => now()->subYear()->toDateString(), 'expiration_date' => now()->addYear()->toDateString(),
            'document' => UploadedFile::fake()->createWithContent('certificat.pdf', '%PDF-1.4 demo'),
        ]);
        $certification = Certification::latest('id')->firstOrFail();
        $response->assertSessionHas('warning', __('ai.certification.producer_warning'));

        // The proof itself went to the model, with the duplicates count and what the certificate covers.
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/certification/risk')
            && $request['certificate']['document']['base64'] === base64_encode('%PDF-1.4 demo')
            && $request['certificate']['duplicates'] === 0
            && $request['certificate']['target']['name'] === $product->name);

        $this->actingAs($this->producer)->get("/producteur/certifications/{$certification->id}")
            ->assertSee(__('ai.certification.owner_title'))
            ->assertSee('ne suit pas le format', false)
            ->assertDontSee(__('ai.certification.explain'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/certifications')->assertOk()->assertSee(__('ai.certification.levels.moyen'));
        $this->actingAs($admin)->get("/admin/certifications/{$certification->id}")
            ->assertSee(__('ai.certification.title'))
            ->assertSee(__('ai.certification.explain'));

        $this->actingAs($admin)->post("/admin/certifications/{$certification->id}/expliquer")->assertSessionHas('success');
        $this->assertSame('Ce certificat présente quelques points à vérifier.', CertificationAnalysis::where('certification_id', $certification->id)->latest('id')->first()->explanation);
        $this->actingAs($admin)->get("/admin/certifications/{$certification->id}")->assertSee('Ce certificat présente quelques points à vérifier.');
    }

    public function test_a_genuine_certificate_does_not_bother_the_producer(): void
    {
        $this->fakeService('faible');
        $product = Product::factory()->create(['created_by' => $this->producer->id]);

        $this->actingAs($this->producer)->post('/producteur/certifications', [
            'target' => "product:{$product->id}", 'name' => 'Agriculture biologique', 'type' => 'BIO', 'issuing_organization' => 'Ecocert',
            'certificate_number' => 'TN-BIO-2025-0412', 'issue_date' => now()->subYear()->toDateString(),
        ])->assertSessionMissing('warning');

        $this->actingAs($this->producer)->get('/producteur/certifications/'.Certification::latest('id')->value('id'))
            ->assertDontSee(__('ai.certification.owner_title'));
    }

    public function test_certificates_are_submitted_normally_when_the_service_is_off(): void
    {
        $this->fakeServiceDown();
        $product = Product::factory()->create(['created_by' => $this->producer->id]);

        $this->actingAs($this->producer)->post('/producteur/certifications', [
            'target' => "product:{$product->id}", 'name' => 'Agriculture biologique', 'type' => 'BIO', 'issuing_organization' => 'Ecocert',
            'issue_date' => now()->subYear()->toDateString(),
        ])->assertSessionHas('success')->assertSessionMissing('warning');

        $this->assertSame(0, CertificationAnalysis::count());
        $this->actingAs(User::factory()->admin()->create())->get('/admin/certifications')->assertOk()->assertSee(__('ai.certification.not_analyzed'));
    }

    // --- admin page and commands ---------------------------------------------------------------

    public function test_the_admin_follows_and_starts_a_training_run(): void
    {
        Http::fake([
            '*/health' => Http::response(['ready' => true, 'chat' => true, 'anomaly' => true, 'certification' => true, 'reception' => true, 'adapters' => ['transport'], 'model' => 'qwen2.5-1.5b-instruct', 'training' => false]),
            '*/models' => Http::response(['adapters' => [
                ['module' => 'transport', 'label' => 'Transport', 'trained' => true, 'training' => ['trained_at' => '2026-10-17T10:00:00', 'base_model' => 'Qwen/Qwen2.5-1.5B-Instruct', 'train_examples' => 3359, 'train_loss' => 0.12, 'eval_loss' => 0.017], 'evaluation' => ['accuracy' => 0.93, 'questions' => 150]],
                ['module' => 'distribution', 'label' => 'Distribution', 'trained' => false],
            ], 'classifiers' => ['anomaly' => ['loaded' => true, 'metrics' => ['accuracy' => 0.999, 'macro_f1' => 0.999, 'real_accuracy' => 1.0]], 'cert' => ['loaded' => true, 'metrics' => null], 'reception' => ['loaded' => true, 'metrics' => null]]]),
            '*/train/status' => Http::response(['state' => 'running', 'running' => true, 'module' => 'distribution', 'stage' => 'llm', 'step' => 40, 'total' => 158, 'log' => []]),
            '*/train/cancel' => Http::response(['state' => 'cancelled', 'running' => false]),
            '*/train' => Http::response(['state' => 'running', 'running' => true]),
        ]);
        $admin = User::factory()->admin()->create();
        // Starting a run exports the data first: not over the developer's real export.
        $exportFolder = storage_path('framework/testing/ai-export-admin');
        config(['ai.export_path' => $exportFolder]);

        $this->actingAs($admin)->get('/admin/ia')->assertOk()
            ->assertSee(__('ai.admin.title'))
            ->assertSee('qwen2.5-1.5b-instruct')
            ->assertSee(__('ai.admin.not_trained'))
            ->assertSee('93,0 %', false);
        $this->actingAs($admin)->getJson('/admin/ia/statut')->assertJson(['state' => 'running', 'step' => 40]);

        $this->actingAs($admin)->post('/admin/ia/entrainer', ['module' => 'distribution', 'epochs' => 1])->assertSessionHas('success');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/train') && $request['module'] === 'distribution');
        $this->assertFileExists($exportFolder.'/distributors.json');
        File::deleteDirectory($exportFolder);

        // Resuming keeps the dataset of the interrupted run: no new export.
        $this->actingAs($admin)->post('/admin/ia/entrainer', ['module' => 'distribution', 'resume' => 1])->assertSessionHas('success');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/train') && $request['resume'] === true);
        $this->assertDirectoryDoesNotExist($exportFolder);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.training_started']);

        $this->actingAs($admin)->post('/admin/ia/annuler')->assertSessionHas('success', __('ai.admin.cancelled'));
        $this->actingAs($admin)->post('/admin/ia/entrainer', ['module' => 'autre'])->assertSessionHasErrors('module');
        $this->actingAs($this->distributor)->get('/admin/ia')->assertForbidden();
    }

    public function test_demo_certificates_get_their_own_readable_proof(): void
    {
        Storage::disk('local')->put('certifications/demo-certificat.pdf', '%PDF-1.4 shared');
        $certification = Certification::factory()->create(['certificate_number' => 'TN-BIO-2025-0412']);
        $certification->forceFill(['document_path' => 'certifications/demo-certificat.pdf'])->save();

        $this->artisan('ai:demo-certificates')->assertSuccessful();

        $path = $certification->fresh()->document_path;
        $this->assertSame("certifications/demo/certificat-{$certification->id}.pdf", $path);
        $pdf = Storage::disk('local')->get($path);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('TN-BIO-2025-0412', $pdf);
        $this->assertStringContainsString('xref', $pdf);
    }

    public function test_the_export_includes_the_distribution_data(): void
    {
        $folder = storage_path('framework/testing/ai-export-distribution');

        $this->artisan('ai:export', ['--output' => $folder])->assertSuccessful();

        $distributors = json_decode(File::get($folder.'/distributors.json'), true);
        $this->assertCount(1, $distributors);
        $this->assertSame(['today', 'distributor', 'distributions', 'sales'], array_keys($distributors[0]));
        $this->assertCount(Distribution::count(), json_decode(File::get($folder.'/receptions.json'), true));
        $this->assertFileExists($folder.'/certifications.json');

        File::deleteDirectory($folder);
    }
}
