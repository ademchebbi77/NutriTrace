<?php

namespace Tests\Feature\Modules;

use App\Enums\DistributionStatus;
use App\Enums\LotStatus;
use App\Enums\TransportStatus;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\User;
use App\Services\LotDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsJourneys;
use Tests\TestCase;

/**
 * The distributor's side of the chain, driven through the web interface.
 * Lots are sent with the real dispatcher, as the Lot module does.
 */
class DistributionTest extends TestCase
{
    use BuildsJourneys, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createActors();
    }

    public function test_the_distributor_receives_shelves_and_sells(): void
    {
        $oil = $this->createOilLot();
        $distribution = $this->sendToDistributor($oil, $this->transformer, ['distance_km' => 270]);

        $this->assertSame(DistributionStatus::PENDING, $distribution->status);
        $this->assertSame(270.0, $distribution->transport->distance_km);
        // The holder does not change until the distributor confirms.
        $this->assertTrue($oil->fresh()->isHeldBy($this->transformer));

        $this->actingAs($this->distributor)->get('/distributeur/receptions')->assertOk()->assertSee($oil->lot_number);
        $this->actingAs($this->distributor)->get("/distributeur/distributions/{$distribution->id}")->assertOk()->assertSee(__('distributions.to_receive'));

        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$distribution->id}/recevoir", ['destination' => 'Magasin Lafayette, Tunis'])
            ->assertRedirect("/distributeur/distributions/{$distribution->id}");

        $this->assertTrue($oil->fresh()->isHeldBy($this->distributor));
        $this->assertSame(LotStatus::DISTRIBUTED, $oil->fresh()->status);
        $this->assertSame(DistributionStatus::RECEIVED, $distribution->fresh()->status);
        $this->assertSame('Magasin Lafayette, Tunis', $distribution->fresh()->destination);
        $this->assertSame(TransportStatus::DELIVERED, $distribution->transport->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lot.received']);

        // Nothing can be sold before the lot is on the shelves.
        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$distribution->id}/ventes", ['quantity' => 1])->assertForbidden();

        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$distribution->id}/mise-en-rayon")->assertSessionHas('success');
        $this->assertSame(LotStatus::IN_STORE, $oil->fresh()->status);
        $this->assertSame(DistributionStatus::IN_STORE, $distribution->fresh()->status);

        $this->actingAs($this->distributor)->get('/distributeur/ventes')->assertOk()->assertSee($oil->lot_number);
        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$distribution->id}/ventes", ['quantity' => 100])->assertSessionHas('success');
        $this->assertSame(800.0, $oil->fresh()->quantity);

        $this->actingAs($this->distributor)->get("/distributeur/distributions/{$distribution->id}")->assertOk()->assertSee(__('distributions.sales.record'));
        $this->actingAs($this->distributor)->get('/distributeur/distributions')->assertOk()->assertSee($oil->lot_number);
        $this->actingAs($this->distributor)->get('/distributeur')->assertOk();
    }

    public function test_a_sale_cannot_exceed_the_stock_and_the_last_one_closes_the_lot(): void
    {
        $this->buildOliveOilJourney();
        $url = "/distributeur/distributions/{$this->distribution->id}/ventes";

        $this->actingAs($this->distributor)->post($url, ['quantity' => 5000])->assertSessionHasErrors('quantity');
        $this->actingAs($this->distributor)->post($url, ['quantity' => 0])->assertSessionHasErrors('quantity');
        $this->actingAs($this->distributor)->post($url, [])->assertSessionHasErrors('quantity');
        $this->assertSame(900.0, $this->oilLot->fresh()->quantity);

        $this->actingAs($this->distributor)->post($url, ['quantity' => 900])->assertSessionHas('success');

        $this->assertSame(0.0, $this->oilLot->fresh()->quantity);
        $this->assertSame(LotStatus::SOLD_OUT, $this->oilLot->fresh()->status);

        // Nothing left to sell.
        $this->actingAs($this->distributor)->post($url, ['quantity' => 1])->assertForbidden();
    }

    public function test_a_distributor_can_refuse_a_distribution(): void
    {
        $lot = $this->createOliveLot();
        $distribution = $this->sendToDistributor($lot, $this->producer);

        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$distribution->id}/refuser", ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$distribution->id}/refuser", ['rejection_reason' => 'Livraison hors délai.'])
            ->assertSessionHas('success');

        $this->assertSame(DistributionStatus::REJECTED, $distribution->fresh()->status);
        $this->assertSame(TransportStatus::CANCELLED, $distribution->transport->fresh()->status);
        $this->assertTrue($lot->fresh()->isHeldBy($this->producer));
        $this->assertSame(LotStatus::CREATED, $lot->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lot.refused']);

        $this->actingAs($this->distributor)->get("/distributeur/distributions/{$distribution->id}")->assertOk()->assertSee('Livraison hors délai.');

        // A hand-over is answered once.
        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$distribution->id}/recevoir")->assertForbidden();
    }

    public function test_only_the_distributor_the_lot_was_sent_to_can_act_on_it(): void
    {
        $lot = $this->createOliveLot();
        $distribution = $this->sendToDistributor($lot, $this->producer);
        $stranger = User::factory()->distributor()->create();

        $this->actingAs($stranger)->get("/distributeur/distributions/{$distribution->id}")->assertForbidden();
        $this->actingAs($stranger)->post("/distributeur/distributions/{$distribution->id}/recevoir")->assertForbidden();
        $this->actingAs($stranger)->post("/distributeur/distributions/{$distribution->id}/refuser", ['rejection_reason' => 'Pas pour moi.'])->assertForbidden();
        $this->actingAs($stranger)->get('/distributeur/receptions')->assertOk()->assertDontSee($lot->lot_number);
        $this->actingAs($stranger)->get('/distributeur/distributions')->assertOk()->assertDontSee($lot->lot_number);

        // The distributor area is closed to the other roles, even to the sender.
        $this->actingAs($this->producer)->get('/distributeur/distributions')->assertForbidden();

        $this->assertSame(DistributionStatus::PENDING, $distribution->fresh()->status);
    }

    public function test_admin_reads_the_distributions_but_does_not_sell(): void
    {
        $this->buildOliveOilJourney();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/distributions')->assertOk()->assertSee($this->oilLot->lot_number);
        $this->actingAs($admin)->get("/admin/distributions/{$this->distribution->id}")->assertOk();
        $this->actingAs($admin)->get('/admin')->assertOk();

        $this->actingAs($admin)->post("/distributeur/distributions/{$this->distribution->id}/ventes", ['quantity' => 1])->assertForbidden();
    }

    /**
     * 900 L of oil held by the transformer, made from the reference olive lot.
     */
    private function createOilLot(): Lot
    {
        $olives = $this->createOliveLot();
        $this->transferToTransformer($olives);

        return $this->transform($olives->fresh())->outputLot;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function sendToDistributor(Lot $lot, User $sender, array $overrides = []): Distribution
    {
        return app(LotDispatcher::class)->send($lot, $sender, $this->distributor, $overrides + [
            'transport_type' => 'truck',
            'departure_date' => '2025-12-01 07:00',
        ]);
    }
}
