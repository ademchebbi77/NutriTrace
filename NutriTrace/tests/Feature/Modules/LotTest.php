<?php

namespace Tests\Feature\Modules;

use App\Enums\LotStatus;
use App\Models\Lot;
use App\Models\Production;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_actor_sees_the_lots_it_holds_or_produced(): void
    {
        $producer = User::factory()->producer()->create();
        $transformer = User::factory()->transformer()->create();

        $held = Production::factory()->by($producer)->create()->lot;
        $transferred = Production::factory()->by($producer)->create()->lot;
        $transferred->forceFill(['current_holder_id' => $transformer->id])->save();
        $foreign = Production::factory()->create()->lot;

        $this->actingAs($producer)->get('/producteur/lots')
            ->assertOk()
            ->assertSee($held->lot_number)
            ->assertSee($transferred->lot_number)
            ->assertDontSee($foreign->lot_number);

        $this->actingAs($transformer)->get('/transformateur/lots')
            ->assertOk()
            ->assertSee($transferred->lot_number)
            ->assertDontSee($held->lot_number);

        $this->actingAs($producer)->get("/producteur/lots/{$transferred->id}")->assertOk();
        $this->actingAs($producer)->get("/producteur/lots/{$foreign->id}")->assertForbidden();
        $this->actingAs($transformer)->get("/transformateur/lots/{$held->id}")->assertForbidden();
    }

    public function test_only_the_current_holder_can_act_on_a_lot(): void
    {
        $producer = User::factory()->producer()->create();
        $transformer = User::factory()->transformer()->create();
        $lot = Production::factory()->by($producer)->create(['production_date' => '2026-01-10'])->lot;

        $this->actingAs($producer)
            ->put("/producteur/lots/{$lot->id}", ['expiration_date' => '2026-06-30'])
            ->assertRedirect("/producteur/lots/{$lot->id}");
        $this->assertSame('2026-06-30', $lot->fresh()->expiration_date->toDateString());

        // Once the lot has changed hands, its producer can still read it but no longer act on it.
        $lot->forceFill(['current_holder_id' => $transformer->id])->save();

        $this->actingAs($producer)->get("/producteur/lots/{$lot->id}/edit")->assertForbidden();
        $this->actingAs($producer)->put("/producteur/lots/{$lot->id}", ['expiration_date' => '2027-01-01'])->assertForbidden();
        $this->actingAs($transformer)->get("/transformateur/lots/{$lot->id}/edit")->assertOk();
    }

    public function test_the_expiration_date_must_follow_the_production_date(): void
    {
        $producer = User::factory()->producer()->create();
        $lot = Production::factory()->by($producer)->create(['production_date' => '2026-01-10'])->lot;

        $this->actingAs($producer)
            ->put("/producteur/lots/{$lot->id}", ['expiration_date' => '2026-01-01'])
            ->assertSessionHasErrors('expiration_date');
    }

    public function test_protected_lot_fields_cannot_be_mass_assigned(): void
    {
        $producer = User::factory()->producer()->create();
        $lot = Production::factory()->by($producer)->create()->lot;

        $this->actingAs($producer)->put("/producteur/lots/{$lot->id}", [
            'expiration_date' => now()->addYear()->toDateString(),
            'quantity' => 999999,
            'status' => 'sold_out',
            'lot_number' => 'LOT-HACK',
            'current_holder_id' => User::factory()->create()->id,
        ]);

        $fresh = $lot->fresh();

        $this->assertSame($lot->quantity, $fresh->quantity);
        $this->assertSame(LotStatus::CREATED, $fresh->status);
        $this->assertSame($lot->lot_number, $fresh->lot_number);
        $this->assertTrue($fresh->isHeldBy($producer));
    }

    public function test_lots_cannot_be_created_or_deleted_by_hand(): void
    {
        $producer = User::factory()->producer()->create();
        $lot = Production::factory()->by($producer)->create()->lot;

        $this->actingAs($producer)->post('/producteur/lots', [])->assertStatus(405);
        $this->actingAs($producer)->delete("/producteur/lots/{$lot->id}")->assertStatus(405);
    }

    public function test_admin_reads_every_lot(): void
    {
        $admin = User::factory()->admin()->create();
        $lot = Production::factory()->create()->lot;

        $this->actingAs($admin)->get('/admin/lots')->assertOk()->assertSee($lot->lot_number);
        $this->actingAs($admin)->get("/admin/lots/{$lot->id}")->assertOk()->assertSee($lot->product->name);
    }

    public function test_consumers_have_no_lot_back_office(): void
    {
        $this->actingAs(User::factory()->create())->get('/producteur/lots')->assertForbidden();
    }

    public function test_a_standalone_lot_gets_its_number_and_token(): void
    {
        $lot = Lot::factory()->create(['production_date' => '2026-05-01']);

        $this->assertSame('LOT-2026-001', $lot->lot_number);
        $this->assertNull($lot->production_id);
        $this->assertSame($lot->quantity, $lot->initial_quantity);
        $this->assertNotEmpty($lot->public_token);
    }
}
