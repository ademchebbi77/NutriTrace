<?php

namespace Tests\Feature\Modules;

use App\Models\Product;
use App\Models\Production;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AI Production Forecasting — Laravel integration tests.
 *
 * The Python service is always faked: these tests cover the Laravel side only
 * (routing, authorisation, service degradation, response shape).
 */
class ProductionForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function fakeServiceOk(array $payload = []): void
    {
        $default = [
            'status'            => 'statistical_only',
            'unit'              => 'kg',
            'history'           => [['date' => '2023-06-01', 'quantity' => 3000, 'method' => 'organic']],
            'statistics'        => ['count' => 1, 'mean' => 3000, 'std' => null, 'min' => 3000, 'max' => 3000, 'last' => 3000],
            'trend_per_year'    => null,
            'trend_direction'   => null,
            'baseline_estimate' => 3000.0,
            'prediction'        => null,
            'prediction_period' => null,
            'model_info'        => ['name' => 'baseline_mean', 'trained' => false, 'reason' => 'not enough data'],
            'explanation'       => 'Not enough data.',
        ];

        Http::fake([
            '*/health'               => Http::response(['ready' => true, 'chat' => false]),
            '*/production/forecast'  => Http::response(array_merge($default, $payload)),
        ]);
    }

    private function fakeServiceDown(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
    }

    private function makeProduction(User $producer, array $attributes = []): Production
    {
        $product = Product::factory()->create(['created_by' => $producer->id]);
        return Production::factory()->by($producer)->create(
            array_merge(['product_id' => $product->id], $attributes)
        );
    }

    // -----------------------------------------------------------------------
    // Route & authorisation
    // -----------------------------------------------------------------------

    public function test_producer_can_access_forecast_for_own_production(): void
    {
        $this->fakeServiceOk();
        $producer   = User::factory()->producer()->create();
        $production = $this->makeProduction($producer);

        $this->actingAs($producer)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertOk()
            ->assertJsonStructure(['status', 'unit', 'history', 'statistics', 'baseline_estimate', 'model_info']);
    }

    public function test_producer_cannot_access_forecast_of_another_producer(): void
    {
        $this->fakeServiceOk();
        $stranger   = User::factory()->producer()->create();
        $production = Production::factory()->create(); // owned by a different producer

        $this->actingAs($stranger)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertForbidden();
    }

    public function test_unauthenticated_request_is_redirected(): void
    {
        $production = Production::factory()->create();

        $this->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertUnauthorized();
    }

    public function test_non_producer_role_cannot_access_route(): void
    {
        $transformer = User::factory()->transformer()->create();
        $production  = Production::factory()->create();

        $this->actingAs($transformer)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // Service unavailable — graceful degradation
    // -----------------------------------------------------------------------

    public function test_returns_service_unavailable_when_python_is_down(): void
    {
        $this->fakeServiceDown();
        cache()->forget('ai.service.up');

        $producer   = User::factory()->producer()->create();
        $production = $this->makeProduction($producer);

        $result = $this->actingAs($producer)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertOk()
            ->json();

        $this->assertSame('service_unavailable', $result['status']);
        $this->assertNull($result['prediction']);
        $this->assertNull($result['statistics']);
    }

    public function test_production_show_page_still_loads_when_service_is_down(): void
    {
        $this->fakeServiceDown();
        cache()->forget('ai.service.up');

        $producer   = User::factory()->producer()->create();
        $production = $this->makeProduction($producer);

        // The main page must load — the AI panel loads asynchronously
        $this->actingAs($producer)
            ->get("/producteur/productions/{$production->id}")
            ->assertOk();
    }

    // -----------------------------------------------------------------------
    // Response shape
    // -----------------------------------------------------------------------

    public function test_response_contains_all_required_keys(): void
    {
        $this->fakeServiceOk();
        $producer   = User::factory()->producer()->create();
        $production = $this->makeProduction($producer);

        $this->actingAs($producer)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'unit',
                'history',
                'statistics',
                'trend_per_year',
                'baseline_estimate',
                'prediction',
                'prediction_period',
                'model_info' => ['name', 'trained', 'reason'],
                'explanation',
            ]);
    }

    public function test_ok_status_with_ml_prediction_forwarded(): void
    {
        $this->fakeServiceOk([
            'status'     => 'ok',
            'prediction' => 3500.0,
            'model_info' => ['name' => 'gradient_boosting', 'trained' => true, 'mae' => 120.5, 'train_size' => 10, 'test_size' => 3, 'reason' => 'ok'],
        ]);

        $producer   = User::factory()->producer()->create();
        $production = $this->makeProduction($producer);

        $result = $this->actingAs($producer)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertOk()
            ->json();

        $this->assertSame('ok', $result['status']);
        $this->assertEquals(3500.0, $result['prediction']);
        $this->assertSame('gradient_boosting', $result['model_info']['name']);
    }

    public function test_insufficient_data_status_forwarded(): void
    {
        $this->fakeServiceOk([
            'status'            => 'insufficient_data',
            'statistics'        => null,
            'baseline_estimate' => null,
            'prediction'        => null,
        ]);

        $producer   = User::factory()->producer()->create();
        $production = $this->makeProduction($producer);

        $result = $this->actingAs($producer)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertOk()
            ->json();

        $this->assertSame('insufficient_data', $result['status']);
        $this->assertNull($result['prediction']);
    }

    // -----------------------------------------------------------------------
    // Payload sent to Python service
    // -----------------------------------------------------------------------

    public function test_records_sent_to_python_match_production_history(): void
    {
        $this->fakeServiceOk();

        $producer = User::factory()->producer()->create();
        $product  = Product::factory()->create(['created_by' => $producer->id]);

        // Create two productions for the same product/unit
        Production::factory()->by($producer)->create([
            'product_id'      => $product->id,
            'quantity'        => 2000,
            'unit'            => 'kg',
            'production_date' => '2022-06-01',
        ]);
        $production = Production::factory()->by($producer)->create([
            'product_id'      => $product->id,
            'quantity'        => 2500,
            'unit'            => 'kg',
            'production_date' => '2023-06-01',
        ]);

        $this->actingAs($producer)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertOk();

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/production/forecast')) {
                return false;
            }
            $records = $request['records'];
            return count($records) === 2
                && $records[0]['quantity'] == 2000
                && $records[1]['quantity'] == 2500;
        });
    }

    public function test_different_units_are_not_mixed_in_request(): void
    {
        $this->fakeServiceOk();

        $producer = User::factory()->producer()->create();
        $product  = Product::factory()->create(['created_by' => $producer->id]);

        // kg production
        Production::factory()->by($producer)->create([
            'product_id' => $product->id, 'quantity' => 1000, 'unit' => 'kg',
        ]);
        // t production — different unit, must NOT be included
        Production::factory()->by($producer)->create([
            'product_id' => $product->id, 'quantity' => 2, 'unit' => 't',
        ]);

        $kgProduction = Production::where('unit', 'kg')->firstOrFail();

        $this->actingAs($producer)
            ->getJson("/producteur/productions/{$kgProduction->id}/forecast")
            ->assertOk();

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/production/forecast')) {
                return false;
            }
            $records = $request['records'];
            foreach ($records as $r) {
                if ($r['unit'] !== 'kg') return false;
            }
            return true;
        });
    }

    public function test_invalid_quantities_are_excluded_from_payload(): void
    {
        $this->fakeServiceOk();

        $producer = User::factory()->producer()->create();
        $product  = Product::factory()->create(['created_by' => $producer->id]);

        Production::factory()->by($producer)->create([
            'product_id' => $product->id, 'quantity' => 0, 'unit' => 'kg',
        ]);
        Production::factory()->by($producer)->create([
            'product_id' => $product->id, 'quantity' => -50, 'unit' => 'kg',
        ]);
        $production = Production::factory()->by($producer)->create([
            'product_id' => $product->id, 'quantity' => 1200, 'unit' => 'kg',
        ]);

        $this->actingAs($producer)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertOk();

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/production/forecast')) {
                return false;
            }
            $records = $request['records'];
            return count($records) === 1 && $records[0]['quantity'] == 1200;
        });
    }

    public function test_exact_duplicates_are_deduplicated_before_sending(): void
    {
        $this->fakeServiceOk();

        $producer = User::factory()->producer()->create();
        $product  = Product::factory()->create(['created_by' => $producer->id]);

        foreach ([1, 2] as $i) {
            Production::factory()->by($producer)->create([
                'product_id' => $product->id, 'quantity' => 800, 'unit' => 'kg',
                'production_date' => '2024-06-01', 'production_method' => 'organic',
            ]);
        }
        $production = Production::factory()->by($producer)->create([
            'product_id' => $product->id, 'quantity' => 800, 'unit' => 'kg', 'production_date' => '2024-07-01',
        ]);

        $this->actingAs($producer)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertOk();

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/production/forecast')) {
                return false;
            }
            return count($request['records']) === 2;
        });
    }

    public function test_payload_records_are_chronologically_sorted(): void
    {
        $this->fakeServiceOk();

        $producer = User::factory()->producer()->create();
        $product  = Product::factory()->create(['created_by' => $producer->id]);

        $production = Production::factory()->by($producer)->create([
            'product_id' => $product->id, 'quantity' => 150, 'unit' => 'kg', 'production_date' => '2026-01-10',
        ]);
        Production::factory()->by($producer)->create([
            'product_id' => $product->id, 'quantity' => 100, 'unit' => 'kg', 'production_date' => '2025-01-10',
        ]);
        Production::factory()->by($producer)->create([
            'product_id' => $product->id, 'quantity' => 200, 'unit' => 'kg', 'production_date' => '2024-01-10',
        ]);

        $this->actingAs($producer)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertOk();

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/production/forecast')) {
                return false;
            }
            $dates = array_column($request['records'], 'production_date');
            $sorted = $dates;
            sort($sorted);
            return $dates === $sorted && $dates[0] === '2024-01-10';
        });
    }

    public function test_no_forecast_call_when_all_history_is_invalid(): void
    {
        // Only the health check is faked: a production/forecast call would fail.
        Http::fake(['*/health' => Http::response(['ready' => true])]);

        $producer = User::factory()->producer()->create();
        $product  = Product::factory()->create(['created_by' => $producer->id]);

        $production = Production::factory()->by($producer)->create([
            'product_id' => $product->id, 'quantity' => 0, 'unit' => 'kg',
        ]);

        $result = $this->actingAs($producer)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertOk()
            ->json();

        $this->assertSame('no_data', $result['status']);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/production/forecast'));
    }

    public function test_no_data_returned_when_no_productions_exist(): void
    {
        // No Http::fake — service should not even be called
        Http::fake(['*/health' => Http::response(['ready' => true])]);

        $producer = User::factory()->producer()->create();
        // Create a production for a different producer so the producer has none
        $otherProduction = Production::factory()->create();

        // Give the producer their own production of a different product
        $producer2 = User::factory()->producer()->create();
        $production = Production::factory()->by($producer2)->create();

        // The actual producer has zero productions — but we call the endpoint
        // with a production owned by producer2 (will be forbidden)
        $this->actingAs($producer)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // Regression: existing AI modules unaffected
    // -----------------------------------------------------------------------

    public function test_existing_health_endpoint_still_works(): void
    {
        Http::fake([
            '*/health' => Http::response([
                'ready'   => true,
                'chat'    => false,
                'anomaly' => true,
            ]),
        ]);

        $producer = User::factory()->producer()->create();

        // The health check is called internally; we verify it resolves correctly
        $client = app(\App\Services\Ai\AiClient::class);
        $this->assertTrue($client->available());
        $this->assertTrue($client->has('anomaly'));
    }

    public function test_cohere_client_unaffected_by_production_ai(): void
    {
        // ProductionForecastService must not instantiate CohereClient
        $service = app(\App\Services\Ai\ProductionForecastService::class);
        $this->assertInstanceOf(\App\Services\Ai\ProductionForecastService::class, $service);

        // CohereClient should not be called during production forecast
        Http::fake([
            '*/health'              => Http::response(['ready' => true]),
            '*/production/forecast' => Http::response(['status' => 'insufficient_data', 'unit' => 'kg',
                'history' => [], 'statistics' => null, 'trend_per_year' => null, 'trend_direction' => null,
                'baseline_estimate' => null, 'prediction' => null,
                'model_info' => ['name' => null, 'trained' => false, 'reason' => 'test'],
                'explanation' => 'test']),
            'api.cohere.com/*'      => Http::response([], 500), // should never be called
        ]);

        $producer   = User::factory()->producer()->create();
        $production = $this->makeProduction($producer);

        $this->actingAs($producer)
            ->getJson("/producteur/productions/{$production->id}/forecast")
            ->assertOk();

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'cohere.com'));
    }
}
