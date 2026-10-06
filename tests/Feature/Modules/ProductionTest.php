<?php

namespace Tests\Feature\Modules;

use App\Enums\LotStatus;
use App\Enums\Unit;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Production;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_a_production_creates_its_lot_automatically(): void
    {
        $producer = User::factory()->producer()->create();
        $product = Product::factory()->create(['created_by' => $producer->id]);

        $this->actingAs($producer)->get('/producteur/productions/create')->assertOk();

        $response = $this->actingAs($producer)->post('/producteur/productions', $this->payload($product, [
            'production_date' => '2026-03-10',
            'expiration_date' => '2026-04-10',
        ]));

        $production = Production::firstOrFail();
        $lot = Lot::firstOrFail();

        $response->assertRedirect("/producteur/productions/{$production->id}");

        $this->assertSame($producer->id, $production->producer_id);
        $this->assertSame(['water_l' => '60000', 'energy_kwh' => '180'], $production->resources_used);

        $this->assertSame('LOT-2026-001', $lot->lot_number);
        $this->assertSame($production->id, $lot->production_id);
        $this->assertSame($product->id, $lot->product_id);
        $this->assertTrue($lot->isHeldBy($producer));
        $this->assertSame(LotStatus::CREATED, $lot->status);
        $this->assertSame(5000.0, $lot->quantity);
        $this->assertSame(5000.0, $lot->initial_quantity);
        $this->assertSame(Unit::KILOGRAM, $lot->unit);
        $this->assertSame('2026-04-10', $lot->expiration_date->toDateString());
        $this->assertSame(32, strlen($lot->public_token));
    }

    public function test_lot_numbers_are_sequential_per_year_and_tokens_unique(): void
    {
        $producer = User::factory()->producer()->create();

        $lots = collect(['2026-01-05', '2026-02-05', '2025-12-20', '2026-03-05'])
            ->map(fn (string $date) => Production::factory()->by($producer)->create(['production_date' => $date])->lot);

        $this->assertSame(
            ['LOT-2026-001', 'LOT-2026-002', 'LOT-2025-001', 'LOT-2026-003'],
            $lots->pluck('lot_number')->all(),
        );
        $this->assertCount(4, $lots->pluck('public_token')->unique());
    }

    public function test_a_producer_can_only_use_their_own_active_products(): void
    {
        $producer = User::factory()->producer()->create();
        $foreign = Product::factory()->create();
        $archived = Product::factory()->archived()->create(['created_by' => $producer->id]);

        $this->actingAs($producer)->post('/producteur/productions', $this->payload($foreign))->assertSessionHasErrors('product_id');
        $this->actingAs($producer)->post('/producteur/productions', $this->payload($archived))->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('lots', 0);
    }

    public function test_production_data_is_validated(): void
    {
        $producer = User::factory()->producer()->create();
        $product = Product::factory()->create(['created_by' => $producer->id]);

        $this->actingAs($producer)->post('/producteur/productions', $this->payload($product, [
            'quantity' => 0,
            'production_date' => now()->addDay()->toDateString(),
            'expiration_date' => '2020-01-01',
            'latitude' => 120,
            'unit' => 'litres',
        ]))->assertSessionHasErrors(['quantity', 'production_date', 'expiration_date', 'latitude', 'unit']);
    }

    public function test_updating_a_production_keeps_its_untouched_lot_in_sync(): void
    {
        $producer = User::factory()->producer()->create();
        $production = Production::factory()->by($producer)->create(['quantity' => 1000]);

        $this->actingAs($producer)->put("/producteur/productions/{$production->id}", $this->payload($production->product, [
            'quantity' => 1250.5,
            'unit' => 'L',
            'production_date' => '2026-02-01',
        ]))->assertRedirect("/producteur/productions/{$production->id}");

        $lot = $production->lot->fresh();

        $this->assertSame(1250.5, $lot->quantity);
        $this->assertSame(1250.5, $lot->initial_quantity);
        $this->assertSame(Unit::LITRE, $lot->unit);
        $this->assertSame('2026-02-01', $lot->production_date->toDateString());
    }

    public function test_a_production_is_locked_once_its_lot_has_moved_on(): void
    {
        $producer = User::factory()->producer()->create();
        $production = Production::factory()->by($producer)->create();

        $production->lot->forceFill(['current_holder_id' => User::factory()->transformer()->create()->id])->save();

        $this->actingAs($producer)->get("/producteur/productions/{$production->id}")
            ->assertOk()
            ->assertSee(__('productions.locked'));

        $this->actingAs($producer)->get("/producteur/productions/{$production->id}/edit")->assertForbidden();
        $this->actingAs($producer)->delete("/producteur/productions/{$production->id}")->assertForbidden();
    }

    public function test_deleting_an_untouched_production_removes_its_lot(): void
    {
        $producer = User::factory()->producer()->create();
        $production = Production::factory()->by($producer)->create();

        $this->actingAs($producer)->delete("/producteur/productions/{$production->id}")
            ->assertRedirect('/producteur/productions');

        $this->assertDatabaseCount('productions', 0);
        $this->assertDatabaseCount('lots', 0);
    }

    public function test_producers_cannot_touch_the_productions_of_others(): void
    {
        $producer = User::factory()->producer()->create();
        $other = Production::factory()->create();

        $this->actingAs($producer)->get('/producteur/productions')->assertOk()->assertDontSee($other->lot->lot_number);
        $this->actingAs($producer)->get("/producteur/productions/{$other->id}")->assertForbidden();
        $this->actingAs($producer)->put("/producteur/productions/{$other->id}", $this->payload($other->product))->assertForbidden();
        $this->actingAs($producer)->delete("/producteur/productions/{$other->id}")->assertForbidden();
    }

    public function test_only_producers_can_record_productions(): void
    {
        $this->actingAs(User::factory()->transformer()->create())->get('/producteur/productions')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/producteur/productions/create')->assertForbidden();
    }

    public function test_the_producer_dashboard_shows_its_statistics(): void
    {
        $producer = User::factory()->producer()->create();
        Production::factory()->by($producer)->count(2)->create();

        $this->actingAs($producer)->get('/producteur')
            ->assertOk()
            ->assertSee(__('lots.dashboard.per_month'))
            ->assertViewHas('stats', fn (array $stats) => $stats['productions'] === 2 && $stats['lots_held'] === 2);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Product $product, array $overrides = []): array
    {
        return $overrides + [
            'product_id' => $product->id,
            'location_city' => 'Sfax',
            'latitude' => 34.7406,
            'longitude' => 10.7603,
            'production_date' => '2026-03-10',
            'quantity' => 5000,
            'unit' => 'kg',
            'production_method' => 'organic',
            'resources_used' => ['water_l' => '60000', 'energy_kwh' => '180', 'fertilizer_kg' => '', 'notes' => ''],
        ];
    }
}
