<?php

namespace Tests\Feature\Modules;

use App\Enums\DistributionStatus;
use App\Enums\LotStatus;
use App\Enums\TransferStatus;
use App\Enums\TransportStatus;
use App\Models\Distribution;
use App\Models\LotTransfer;
use App\Models\Transport;
use App\Models\User;
use App\Services\Traceability\ChainVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsJourneys;
use Tests\TestCase;

/**
 * CRUD of the Transport module, driven through the web interface.
 */
class TransportTest extends TestCase
{
    use BuildsJourneys, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createActors();
    }

    public function test_a_sender_creates_a_transport_by_picking_a_lot(): void
    {
        $lot = $this->createOliveLot();

        $this->actingAs($this->producer)->get('/producteur/transports/create')
            ->assertOk()
            ->assertSee($lot->lot_number)
            ->assertSee($this->transformer->displayName());

        $response = $this->actingAs($this->producer)->post('/producteur/transports', [
            'lot_id' => $lot->id,
            'recipient_id' => $this->transformer->id,
            'transport_type' => 'van',
            'departure_date' => '2025-11-21 08:00',
            'distance_km' => 12,
        ]);

        $transport = Transport::firstOrFail();
        $response->assertRedirect("/producteur/transports/{$transport->id}")->assertSessionHas('success');

        $this->assertSame(12.0, $transport->distance_km);
        $this->assertTrue($transport->shipper->is($this->producer));
        $this->assertSame(TransferStatus::PENDING, $transport->transfer->status);
        $this->assertSame(LotStatus::IN_TRANSIT, $lot->fresh()->status);
    }

    public function test_only_an_available_lot_of_the_sender_can_be_shipped(): void
    {
        $lot = $this->createOliveLot();
        $payload = ['lot_id' => $lot->id, 'recipient_id' => $this->transformer->id, 'transport_type' => 'truck', 'departure_date' => '2025-11-21 08:00'];

        $this->actingAs(User::factory()->producer()->create())->post('/producteur/transports', $payload)
            ->assertSessionHasErrors('lot_id');

        // Before its production date.
        $this->actingAs($this->producer)->post('/producteur/transports', ['departure_date' => '2025-11-01 08:00'] + $payload)
            ->assertSessionHasErrors('departure_date');

        $this->actingAs($this->producer)->post('/producteur/transports', $payload)->assertSessionHasNoErrors();

        // Already on the road.
        $this->actingAs($this->producer)->post('/producteur/transports', $payload)->assertSessionHasErrors('lot_id');
        $this->assertSame(1, Transport::count());

        // Distributors never send lots.
        $this->actingAs($this->distributor)->get('/distributeur/transports/create')->assertNotFound();
        $this->actingAs($this->distributor)->post('/distributeur/transports', $payload)->assertMethodNotAllowed();
    }

    public function test_the_shipper_edits_a_transport_on_the_road(): void
    {
        $transport = $this->ship($this->transformer);

        $this->actingAs($this->producer)->get("/producteur/transports/{$transport->id}/edit")->assertOk();
        $this->actingAs($this->transformer)->get("/transformateur/transports/{$transport->id}/edit")->assertForbidden();

        $this->actingAs($this->producer)->put("/producteur/transports/{$transport->id}", ['transport_type' => 'train', 'distance_km' => 40])
            ->assertRedirect("/producteur/transports/{$transport->id}");

        $this->assertSame(40.0, $transport->fresh()->distance_km);
    }

    public function test_deleting_cancels_the_shipment_and_keeps_the_journal(): void
    {
        $transport = $this->ship($this->transformer);
        $lot = $transport->lot;

        $this->actingAs($this->transformer)->delete("/transformateur/transports/{$transport->id}")->assertForbidden();

        $this->actingAs($this->producer)->delete("/producteur/transports/{$transport->id}")
            ->assertRedirect('/producteur/transports')
            ->assertSessionHas('success');

        // The record stays, cancelled, with its hand-over.
        $this->assertSame(TransportStatus::CANCELLED, $transport->fresh()->status);
        $this->assertSame(TransferStatus::CANCELLED, LotTransfer::firstOrFail()->status);

        $lot->refresh();
        $this->assertSame(LotStatus::CREATED, $lot->status);
        $this->assertTrue($lot->isHeldBy($this->producer));

        // Production, departure, withdrawal: a new event, the chain is intact.
        $last = $lot->events()->orderByDesc('id')->first();
        $this->assertSame(3, $lot->events()->count());
        $this->assertSame('withdrawn', $last->metadata['step']);
        $this->assertTrue($last->actor->is($this->producer));
        $this->assertTrue(app(ChainVerifier::class)->verify($lot)->valid);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lot.withdrawn']);

        // The receiver no longer has anything to confirm, and the lot can leave again.
        $this->actingAs($this->transformer)->get('/transformateur/receptions')->assertOk();
        $this->assertSame(0, LotTransfer::where('status', TransferStatus::PENDING)->count());
        $this->actingAs($this->producer)->get("/producteur/lots/{$lot->id}/transferer")->assertOk();

        // Cancelled once, never again.
        $this->actingAs($this->producer)->delete("/producteur/transports/{$transport->id}")->assertForbidden();
    }

    public function test_cancelling_a_shipment_to_a_distributor(): void
    {
        $transport = $this->ship($this->distributor);

        $this->actingAs($this->producer)->delete("/producteur/transports/{$transport->id}")->assertSessionHas('success');

        $this->assertSame(DistributionStatus::CANCELLED, Distribution::firstOrFail()->status);
        $this->assertSame(LotStatus::CREATED, $transport->lot->fresh()->status);
    }

    public function test_a_delivered_transport_can_no_longer_be_changed(): void
    {
        $transport = $this->ship($this->transformer);
        $this->actingAs($this->transformer)->post('/transformateur/receptions/'.LotTransfer::firstOrFail()->id.'/accepter');

        $this->actingAs($this->producer)->get("/producteur/transports/{$transport->id}/edit")->assertForbidden();
        $this->actingAs($this->producer)->delete("/producteur/transports/{$transport->id}")->assertForbidden();
        $this->assertSame(TransportStatus::DELIVERED, $transport->fresh()->status);
    }

    private function ship(User $recipient): Transport
    {
        $lot = $this->createOliveLot();

        $this->actingAs($this->producer)->post('/producteur/transports', [
            'lot_id' => $lot->id,
            'recipient_id' => $recipient->id,
            'transport_type' => 'truck',
            'departure_date' => '2025-11-21 08:00',
        ])->assertSessionHasNoErrors();

        return Transport::latest('id')->firstOrFail();
    }
}
