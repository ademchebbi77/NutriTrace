<?php

namespace Tests\Feature\Modules;

use App\Models\Lot;
use App\Models\LotExpiryCheck;
use App\Models\LotTransfer;
use App\Models\Product;
use App\Models\Transformation;
use App\Models\TransformationCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsJourneys;
use Tests\TestCase;

/**
 * Local AI of the Transformation - Lots module, with the Python service faked.
 */
class TransformationAiTest extends TestCase
{
    use BuildsJourneys, RefreshDatabase;

    private Lot $olives;

    private Product $oil;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.enabled' => true]);
        $this->createActors();

        // Olives held by the transformer, and the oil it can make.
        $this->olives = $this->createOliveLot();
        $this->actingAs($this->producer)->post("/producteur/lots/{$this->olives->id}/transferer", [
            'recipient_id' => $this->transformer->id, 'transport_type' => 'truck', 'departure_date' => '2025-11-21 08:00',
        ]);
        $this->actingAs($this->transformer)->post('/transformateur/receptions/'.LotTransfer::firstOrFail()->id.'/accepter');
        $this->oil = Product::factory()->create(['created_by' => $this->transformer->id, 'name' => 'Huile d\'olive extra vierge']);
    }

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'quantities' => [$this->olives->id => 5000],
            'output_product_id' => $this->oil->id,
            'output_quantity' => 900,
            'output_unit' => 'L',
            'transformation_date' => '2025-11-22',
            'process_description' => 'Trituration et extraction à froid.',
            'energy_used_kwh' => 450,
        ];
    }

    private function fakeService(string $label = 'yield_too_high'): void
    {
        $result = fn (array $payload, bool $explain = false) => [
            'id' => $payload['id'] ?? null, 'is_anomaly' => $label !== 'normal', 'label' => $label, 'confidence' => 0.95,
            'risk' => $label === 'normal' ? 0.02 : 0.93,
            'reasons' => [$label === 'normal' ? '900 L produits, dans la fourchette attendue (700 à 1 150 L).' : '4 000 L produits pour 5 000 kg de matière première, soit un rendement de 80 %.'],
            'expected' => ['profile' => "extraction d'huile d'olive", 'known_profile' => true, 'output_min' => 700.0, 'output_max' => 1150.0,
                'output_unit' => 'L', 'energy_min' => 300, 'energy_max' => 750, 'water_min' => 1500, 'water_max' => 4000],
            'explanation' => $explain ? 'Attention, un point de votre saisie paraît anormal.' : null, 'model' => 'random-forest',
        ];

        Http::fake([
            '*/health' => Http::response(['ready' => true, 'chat' => true, 'transformation' => true, 'shelf_life' => true]),
            '*/transformation/check/batch' => fn (Request $request) => Http::response(['results' => array_map($result, $request['transformations'])]),
            '*/transformation/check' => fn (Request $request) => Http::response($result($request['transformation'], (bool) $request['explain'])),
            '*/lots/expiry/batch' => fn (Request $request) => Http::response(['results' => array_map(fn (array $lot) => [
                'id' => $lot['id'], 'label' => $lot['expiration_date'] ? 'plausible' : 'missing', 'predicted_days' => 15, 'low_days' => 9, 'high_days' => 24,
                'actual_days' => null, 'suggested_date' => '2025-12-05', 'reasons' => ['Aucune date limite renseignée.'],
            ], $request['lots'])]),
        ]);
    }

    public function test_the_transformer_checks_the_form_before_saving(): void
    {
        $this->fakeService();

        $this->actingAs($this->transformer)->get('/transformateur/transformations/create')->assertOk()->assertSee(__('ai.transformation.check'));

        $this->actingAs($this->transformer)->postJson('/transformateur/transformations/verifier', $this->form(['output_quantity' => 4000]))
            ->assertOk()
            ->assertJson(['label' => 'yield_too_high', 'label_text' => __('ai.transformation.labels.yield_too_high'), 'explanation' => 'Attention, un point de votre saisie paraît anormal.'])
            ->assertJsonPath('expected.output_max', 1150);

        // The model received the inputs with their dates and the output, nothing was saved.
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/transformation/check')
            && $request['transformation']['inputs'][0]['lot'] === $this->olives->lot_number
            && $request['transformation']['inputs'][0]['quantity_used'] == 5000
            && $request['transformation']['output']['quantity'] == 4000
            && $request['audience'] === 'transformer');
        $this->assertSame(0, Transformation::count());

        // An incomplete form is reported as such.
        $this->actingAs($this->transformer)->postJson('/transformateur/transformations/verifier', ['output_unit' => 'L'])->assertStatus(422);
        $this->actingAs($this->producer)->postJson('/transformateur/transformations/verifier', $this->form())->assertForbidden();
    }

    public function test_an_anomaly_must_be_confirmed_and_stays_with_the_transformation(): void
    {
        $this->fakeService();

        $this->actingAs($this->transformer)->from('/transformateur/transformations/create')
            ->post('/transformateur/transformations', $this->form(['output_quantity' => 4000]))
            ->assertRedirect('/transformateur/transformations/create')
            ->assertSessionHas('ai_check');
        $this->assertSame(0, Transformation::count());

        $this->actingAs($this->transformer)->get('/transformateur/transformations/create')
            ->assertSee(__('ai.transformation.pending_title'))
            ->assertSee(__('ai.transformation.confirm'));

        $this->actingAs($this->transformer)->post('/transformateur/transformations', $this->form(['output_quantity' => 4000, 'ai_confirmed' => 1]))
            ->assertSessionHasNoErrors();
        $transformation = Transformation::firstOrFail();

        // The check the transformer saw is kept with the transformation.
        $check = TransformationCheck::where('audience', 'transformer')->firstOrFail();
        $this->assertSame($transformation->id, $check->transformation_id);
        $this->actingAs($this->transformer)->get("/transformateur/transformations/{$transformation->id}")
            ->assertSee(__('ai.transformation.title'))
            ->assertSee(__('ai.transformation.labels.yield_too_high'))
            ->assertSee(__('ai.transformation.checked_before_saving'));
    }

    public function test_a_coherent_transformation_is_saved_at_once(): void
    {
        $this->fakeService('normal');

        $this->actingAs($this->transformer)->post('/transformateur/transformations', $this->form())->assertSessionHasNoErrors();

        $this->assertSame(1, Transformation::count());
        $this->assertSame(Transformation::first()->id, TransformationCheck::first()->transformation_id);
    }

    public function test_transformations_are_saved_normally_when_the_service_is_off(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->actingAs($this->transformer)->get('/transformateur/transformations/create')->assertOk()->assertDontSee(__('ai.transformation.check'));
        $this->actingAs($this->transformer)->post('/transformateur/transformations', $this->form(['output_quantity' => 4000]))->assertSessionHasNoErrors();

        $this->assertSame(1, Transformation::count());
        $this->assertSame(0, TransformationCheck::count());
    }

    public function test_the_admin_reviews_transformations_and_lot_dates(): void
    {
        $this->fakeService();
        $this->actingAs($this->transformer)->post('/transformateur/transformations', $this->form(['output_quantity' => 4000, 'ai_confirmed' => 1]));
        $transformation = Transformation::firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/transformations')->assertOk()
            ->assertSee(__('ai.transformation.risk'))
            ->assertSee(__('ai.transformation.labels.yield_too_high'));
        $this->assertSame(1, TransformationCheck::where('audience', 'admin')->count());

        $this->actingAs($admin)->get("/admin/transformations/{$transformation->id}")->assertOk()
            ->assertSee(__('ai.transformation.title'))
            ->assertSee(__('ai.transformation.explain'));
        $this->actingAs($admin)->post("/admin/transformations/{$transformation->id}/expliquer")->assertSessionHas('success');
        $this->assertSame('Attention, un point de votre saisie paraît anormal.', TransformationCheck::where('audience', 'admin')->first()->explanation);

        // Date limits of the lots, for the admin only.
        $this->actingAs($admin)->get('/admin/lots')->assertOk()
            ->assertSee(__('ai.expiry.column'))
            ->assertSee(__('ai.expiry.labels.missing'))
            ->assertSee(__('ai.expiry.suggested', ['date' => '05/12/2025']));
        $this->assertSame(Lot::count(), LotExpiryCheck::count());
        $this->actingAs($this->transformer)->get('/transformateur/lots')->assertOk()->assertDontSee(__('ai.expiry.column'));
        $this->actingAs($this->transformer)->get('/transformateur/transformations')->assertOk()->assertDontSee(__('ai.transformation.risk'));
    }

    public function test_the_export_includes_transformations_and_lot_dates(): void
    {
        $this->fakeService('normal');
        $this->actingAs($this->transformer)->post('/transformateur/transformations', $this->form());
        $folder = storage_path('framework/testing/ai-export-transformation');

        $this->artisan('ai:export', ['--output' => $folder])->assertSuccessful();

        $transformations = json_decode(File::get($folder.'/transformations.json'), true);
        $this->assertSame(['id', 'transformation_date', 'inputs', 'output', 'energy_kwh', 'water_l'], array_keys($transformations[0]));
        $this->assertSame(900.0, (float) $transformations[0]['output']['quantity']);
        $this->assertCount(Lot::count(), json_decode(File::get($folder.'/lots_expiry.json'), true));

        File::deleteDirectory($folder);
    }
}
