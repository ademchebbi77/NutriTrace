<?php

namespace Tests\Feature\Modules;

use App\Enums\EventType;
use App\Enums\TransportStatus;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsignmentNoteTest extends TestCase
{
    use RefreshDatabase;

    private function transport(): Transport
    {
        return Transport::factory()->create(['quantity_transported' => 100]);
    }

    public function test_every_transport_gets_a_note_with_a_public_qr_page(): void
    {
        $transport = $this->transport();

        $this->assertStringStartsWith('LV-', $transport->consignment_number);
        $this->assertSame(40, strlen($transport->consignment_token));

        // Public page: anyone can check the transport, nobody can sign without being the recipient.
        $this->get($transport->consignmentUrl())
            ->assertOk()
            ->assertSee($transport->consignment_number)
            ->assertSee(__('transfers.consignment.pending'))
            ->assertDontSee(__('transfers.consignment.sign_title'));

        // Printable note for the shipper.
        $prefix = $transport->shipper->role->prefix();
        $this->actingAs($transport->shipper)->get("/{$prefix}/transports/{$transport->id}/lettre-de-voiture")
            ->assertOk()
            ->assertSee('<svg', false);
    }

    public function test_the_recipient_signs_and_a_gap_is_journaled(): void
    {
        $transport = $this->transport();
        $recipient = $transport->recipient;

        $this->actingAs($recipient)->get($transport->consignmentUrl())->assertSee(__('transfers.consignment.sign_title'));

        $this->actingAs($recipient)->post(route('consignment.sign', $transport->consignment_token), [
            'handover_quantity' => 95, 'handover_comment' => '5 cagettes abîmées',
        ])->assertSessionHas('warning');

        $transport->refresh();
        $this->assertTrue($transport->isHandedOver());
        $this->assertSame(-5.0, $transport->handoverGap());
        $this->assertTrue($transport->lot->events()->where('event_type', EventType::TRANSPORT)
            ->where('description', 'like', '%'.$transport->consignment_number.'%')->exists());

        // Signed once only.
        $this->actingAs($recipient)->post(route('consignment.sign', $transport->consignment_token), ['handover_quantity' => 100])
            ->assertStatus(409);
    }

    public function test_only_the_recipient_can_sign_and_not_a_cancelled_transport(): void
    {
        $transport = $this->transport();

        $this->actingAs(User::factory()->create())
            ->post(route('consignment.sign', $transport->consignment_token), ['handover_quantity' => 100])
            ->assertForbidden();

        $transport->forceFill(['status' => TransportStatus::CANCELLED])->save();
        $this->actingAs($transport->recipient)
            ->post(route('consignment.sign', $transport->consignment_token), ['handover_quantity' => 100])
            ->assertStatus(409);
        $this->assertFalse($transport->fresh()->isHandedOver());
    }
}
