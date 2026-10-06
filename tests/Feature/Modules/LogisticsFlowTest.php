<?php

namespace Tests\Feature\Modules;

use App\Enums\DistributionStatus;
use App\Enums\LotStatus;
use App\Enums\TransferStatus;
use App\Enums\TransportStatus;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\LotTransfer;
use App\Models\Product;
use App\Models\Transformation;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsJourneys;
use Tests\TestCase;

/**
 * The whole chain driven through the web interface, as the actors would do it.
 */
class LogisticsFlowTest extends TestCase
{
    use BuildsJourneys, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createActors();
    }

    public function test_the_full_transfer_flow_from_farm_to_shelf(): void
    {
        $olives = $this->createOliveLot();

        // 1. The producer sends the lot to the transformer.
        $this->actingAs($this->producer)->get("/producteur/lots/{$olives->id}/transferer")
            ->assertOk()
            ->assertSee($this->transformer->displayName())
            ->assertSee($this->distributor->displayName());

        $this->actingAs($this->producer)->post("/producteur/lots/{$olives->id}/transferer", [
            'recipient_id' => $this->transformer->id,
            'transport_type' => 'truck',
            'departure_date' => '2025-11-21 08:00',
            'note' => 'Récolte du matin',
        ])->assertRedirect('/producteur/transferts');

        $transfer = LotTransfer::firstOrFail();
        $transport = Transport::firstOrFail();

        $this->assertSame(TransferStatus::PENDING, $transfer->status);
        $this->assertSame(LotStatus::IN_TRANSIT, $olives->fresh()->status);
        // The holder does not change until the receiver confirms.
        $this->assertTrue($olives->fresh()->isHeldBy($this->producer));
        $this->assertSame(TransportStatus::IN_TRANSIT, $transport->status);
        // Both ends are in Sfax: Haversine gives 0 km.
        $this->assertSame(0.0, $transport->distance_km);

        $this->actingAs($this->producer)->get('/producteur/transferts')->assertOk()->assertSee($olives->lot_number);

        // 2. The transformer confirms the reception.
        $this->actingAs($this->transformer)->get('/transformateur/receptions')
            ->assertOk()
            ->assertSee($olives->lot_number)
            ->assertSee('Récolte du matin');

        $this->actingAs($this->transformer)->post("/transformateur/receptions/{$transfer->id}/accepter")->assertSessionHas('success');

        $olives->refresh();
        $this->assertTrue($olives->isHeldBy($this->transformer));
        $this->assertSame(LotStatus::IN_TRANSFORMATION, $olives->status);
        $this->assertSame(TransportStatus::DELIVERED, $transport->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lot.received']);

        // 3. The transformer turns the olives into oil.
        $oilProduct = Product::factory()->create(['created_by' => $this->transformer->id, 'name' => 'Huile d\'olive']);

        $this->actingAs($this->transformer)->get('/transformateur/transformations/create')->assertOk()->assertSee($olives->lot_number);

        $this->actingAs($this->transformer)->post('/transformateur/transformations', [
            'quantities' => [$olives->id => 5000],
            'output_product_id' => $oilProduct->id,
            'output_quantity' => 900,
            'output_unit' => 'L',
            'transformation_date' => '2025-11-22',
            'process_description' => 'Trituration et extraction à froid.',
            'energy_used_kwh' => 450,
        ])->assertSessionHasNoErrors();

        $transformation = Transformation::firstOrFail();
        $oil = $transformation->outputLot;

        $this->assertSame(900.0, $oil->quantity);
        $this->assertNull($oil->production_id);
        $this->assertTrue($oil->isHeldBy($this->transformer));
        $this->assertSame(0.0, $olives->fresh()->quantity);
        $this->assertSame(LotStatus::SOLD_OUT, $olives->fresh()->status);
        $this->assertEqualsWithDelta(18.0, $transformation->yieldPercent(), 0.01);

        $this->actingAs($this->transformer)->get("/transformateur/transformations/{$transformation->id}")->assertOk()->assertSee($olives->lot_number);
        $this->actingAs($this->transformer)->get('/transformateur/transformations')->assertOk()->assertSee($oil->lot_number);

        // 4. The transformer ships the oil to the distributor, 270 km by road.
        $this->actingAs($this->transformer)->post("/transformateur/lots/{$oil->id}/transferer", [
            'recipient_id' => $this->distributor->id,
            'transport_type' => 'truck',
            'departure_date' => '2025-12-01 07:00',
            'distance_km' => 270,
        ])->assertRedirect('/transformateur/transferts');

        $distribution = Distribution::firstOrFail();
        $this->assertSame(DistributionStatus::PENDING, $distribution->status);
        $this->assertSame(270.0, $distribution->transport->distance_km);

        // 5. The distributor receives, shelves and sells.
        $this->actingAs($this->distributor)->get('/distributeur/receptions')->assertOk()->assertSee($oil->lot_number);

        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$distribution->id}/recevoir", ['destination' => 'Magasin Lafayette, Tunis'])
            ->assertRedirect("/distributeur/distributions/{$distribution->id}");

        $this->assertTrue($oil->fresh()->isHeldBy($this->distributor));
        $this->assertSame(LotStatus::DISTRIBUTED, $oil->fresh()->status);
        $this->assertSame('Magasin Lafayette, Tunis', $distribution->fresh()->destination);

        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$distribution->id}/mise-en-rayon")->assertSessionHas('success');
        $this->assertSame(LotStatus::IN_STORE, $oil->fresh()->status);

        $this->actingAs($this->distributor)->get('/distributeur/ventes')->assertOk()->assertSee($oil->lot_number);
        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$distribution->id}/ventes", ['quantity' => 100])->assertSessionHas('success');
        $this->assertSame(800.0, $oil->fresh()->quantity);

        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$distribution->id}/ventes", ['quantity' => 5000])->assertSessionHasErrors('quantity');

        // 6. Every actor of the chain can still read the lots it handled.
        $this->actingAs($this->distributor)->get("/distributeur/lots/{$oil->id}")->assertOk()->assertSee(__('lots.journey'));
        $this->actingAs($this->transformer)->get("/transformateur/lots/{$oil->id}")->assertOk();
        $this->actingAs($this->producer)->get("/producteur/lots/{$olives->id}")->assertOk();
        $this->actingAs($this->producer)->get("/producteur/lots/{$oil->id}")->assertForbidden();

        $this->actingAs($this->distributor)->get('/distributeur/etiquettes')->assertOk()->assertSee($oil->lot_number);
        $this->actingAs($this->distributor)->get("/distributeur/lots/{$oil->id}/etiquette")->assertOk()->assertSee('<svg', false);
        $this->actingAs($this->distributor)->get('/distributeur/distributions')->assertOk();
        $this->actingAs($this->distributor)->get('/distributeur')->assertOk();
        $this->actingAs($this->transformer)->get('/transformateur')->assertOk();
        $this->actingAs($this->transformer)->get('/transformateur/transports')->assertOk()->assertSee('270');
    }

    public function test_a_refused_lot_goes_back_to_its_sender(): void
    {
        $olives = $this->createOliveLot();

        $this->actingAs($this->producer)->post("/producteur/lots/{$olives->id}/transferer", [
            'recipient_id' => $this->transformer->id,
            'transport_type' => 'van',
            'departure_date' => '2025-11-21 08:00',
        ]);

        $transfer = LotTransfer::firstOrFail();

        $this->actingAs($this->transformer)->post("/transformateur/receptions/{$transfer->id}/refuser", ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($this->transformer)->post("/transformateur/receptions/{$transfer->id}/refuser", ['rejection_reason' => 'Olives abîmées à l\'arrivée.'])
            ->assertSessionHas('success');

        $olives->refresh();
        $this->assertTrue($olives->isHeldBy($this->producer));
        $this->assertSame(LotStatus::CREATED, $olives->status);
        $this->assertSame(TransferStatus::REJECTED, $transfer->fresh()->status);
        $this->assertSame(TransportStatus::CANCELLED, $transfer->transport->fresh()->status);

        // The producer sees the reason and can send the lot again.
        $this->actingAs($this->producer)->get('/producteur/transferts')->assertSee('Olives abîmées');
        $this->actingAs($this->producer)->get("/producteur/lots/{$olives->id}/transferer")->assertOk();

        // A cancelled transport does not count in the food miles.
        $this->assertSame(0.0, $olives->environmentalImpact->fresh()->food_miles_km);
    }

    public function test_a_distributor_can_refuse_a_distribution(): void
    {
        $lot = $this->createOliveLot();

        $this->actingAs($this->producer)->post("/producteur/lots/{$lot->id}/transferer", [
            'recipient_id' => $this->distributor->id,
            'transport_type' => 'truck',
            'departure_date' => '2025-11-21 08:00',
        ]);

        $distribution = Distribution::firstOrFail();

        $this->actingAs($this->distributor)->post("/distributeur/distributions/{$distribution->id}/refuser", ['rejection_reason' => 'Livraison hors délai.']);

        $this->assertSame(DistributionStatus::REJECTED, $distribution->fresh()->status);
        $this->assertTrue($lot->fresh()->isHeldBy($this->producer));
        $this->assertSame(LotStatus::CREATED, $lot->fresh()->status);
    }

    public function test_only_the_holder_can_send_and_only_an_available_lot(): void
    {
        $lot = $this->createOliveLot();
        $payload = ['recipient_id' => $this->transformer->id, 'transport_type' => 'truck', 'departure_date' => '2025-11-21 08:00'];

        $other = User::factory()->producer()->create();
        $this->actingAs($other)->post("/producteur/lots/{$lot->id}/transferer", $payload)->assertForbidden();

        $this->actingAs($this->producer)->post("/producteur/lots/{$lot->id}/transferer", $payload)->assertRedirect();
        // Already on the road: it cannot be sent twice.
        $this->actingAs($this->producer)->post("/producteur/lots/{$lot->id}/transferer", $payload)->assertForbidden();
        $this->assertSame(1, LotTransfer::count());
    }

    public function test_recipients_must_be_approved_professionals(): void
    {
        $lot = $this->createOliveLot();
        $send = fn (User $recipient) => $this->actingAs($this->producer)->post("/producteur/lots/{$lot->id}/transferer", [
            'recipient_id' => $recipient->id,
            'transport_type' => 'truck',
            'departure_date' => '2025-11-21 08:00',
        ]);

        $send(User::factory()->create())->assertSessionHasErrors('recipient_id');
        $send(User::factory()->transformer()->pending()->create())->assertSessionHasErrors('recipient_id');
        $send(User::factory()->producer()->create())->assertSessionHasErrors('recipient_id');
        $send($this->producer)->assertSessionHasErrors('recipient_id');

        $this->assertSame(LotStatus::CREATED, $lot->fresh()->status);
    }

    public function test_only_the_receiver_can_confirm_a_reception(): void
    {
        $lot = $this->createOliveLot();
        $this->actingAs($this->producer)->post("/producteur/lots/{$lot->id}/transferer", [
            'recipient_id' => $this->transformer->id,
            'transport_type' => 'truck',
            'departure_date' => '2025-11-21 08:00',
        ]);
        $transfer = LotTransfer::firstOrFail();

        $stranger = User::factory()->transformer()->create();
        $this->actingAs($stranger)->post("/transformateur/receptions/{$transfer->id}/accepter")->assertForbidden();
        $this->actingAs($stranger)->get('/transformateur/receptions')->assertOk()->assertDontSee($lot->lot_number);

        $this->actingAs($this->transformer)->post("/transformateur/receptions/{$transfer->id}/accepter");
        // A hand-over is answered once.
        $this->actingAs($this->transformer)->post("/transformateur/receptions/{$transfer->id}/refuser", ['rejection_reason' => 'Trop tard.'])->assertForbidden();
    }

    public function test_a_transformation_checks_its_source_lots(): void
    {
        $olives = $this->createOliveLot();
        $this->transferToTransformer($olives);
        $product = Product::factory()->create(['created_by' => $this->transformer->id]);
        $foreign = Lot::factory()->create();

        $post = fn (array $overrides) => $this->actingAs($this->transformer)->post('/transformateur/transformations', $overrides + [
            'quantities' => [$olives->id => 1000],
            'output_product_id' => $product->id,
            'output_quantity' => 180,
            'output_unit' => 'L',
            'transformation_date' => '2025-11-22',
            'process_description' => 'Extraction à froid en continu.',
        ]);

        $post(['quantities' => []])->assertSessionHasErrors('inputs');
        $post(['quantities' => [$olives->id => 6000]])->assertSessionHasErrors("quantities.{$olives->id}");
        $post(['quantities' => [$foreign->id => 10]])->assertSessionHasErrors('inputs');
        $post(['transformation_date' => '2025-11-01'])->assertSessionHasErrors('transformation_date');
        $post(['output_product_id' => Product::factory()->create()->id])->assertSessionHasErrors('output_product_id');

        $this->assertSame(0, Transformation::count());

        // Two transformations can share one source lot until it is used up.
        $post([])->assertSessionHasNoErrors();
        $post(['quantities' => [$olives->id => 4000]])->assertSessionHasNoErrors();
        $post(['quantities' => [$olives->id => 1]])->assertSessionHasErrors();

        $this->assertSame(2, Transformation::count());
        $this->assertSame(0.0, $olives->fresh()->quantity);
    }

    public function test_several_source_lots_can_feed_one_transformation(): void
    {
        $first = $this->createOliveLot(3000);
        $second = $this->createOliveLot(2000, '2025-11-21');
        $this->transferToTransformer($first);
        $this->transferToTransformer($second, '2025-11-21 14:00', '2025-11-21 16:00');
        $product = Product::factory()->create(['created_by' => $this->transformer->id]);

        $this->actingAs($this->transformer)->post('/transformateur/transformations', [
            'quantities' => [$first->id => 3000, $second->id => 1500],
            'output_product_id' => $product->id,
            'output_quantity' => 810,
            'output_unit' => 'L',
            'transformation_date' => '2025-11-22',
            'process_description' => 'Assemblage de deux récoltes.',
        ])->assertSessionHasNoErrors();

        $transformation = Transformation::with('inputs')->firstOrFail();

        $this->assertCount(2, $transformation->inputs);
        $this->assertSame(4500.0, $transformation->input_quantity);
        $this->assertSame(LotStatus::SOLD_OUT, $first->fresh()->status);
        $this->assertSame(500.0, $second->fresh()->quantity);
        $this->assertSame(LotStatus::IN_TRANSFORMATION, $second->fresh()->status);
    }

    public function test_the_shipper_can_correct_a_transport_on_the_road(): void
    {
        $lot = $this->createOliveLot();
        $this->actingAs($this->producer)->post("/producteur/lots/{$lot->id}/transferer", [
            'recipient_id' => $this->distributor->id,
            'transport_type' => 'truck',
            'departure_date' => '2025-11-21 08:00',
        ]);
        $transport = Transport::firstOrFail();

        $this->actingAs($this->producer)->get("/producteur/transports/{$transport->id}")->assertOk();
        $this->actingAs($this->producer)->put("/producteur/transports/{$transport->id}", ['transport_type' => 'train', 'distance_km' => 300])
            ->assertSessionHas('success');

        $this->assertSame(300.0, $transport->fresh()->distance_km);
        $this->assertSame(300.0, $lot->environmentalImpact->fresh()->food_miles_km);
        // The correction is a new event.
        $this->assertSame(4, $lot->events()->count());

        $this->actingAs($this->distributor)->put("/distributeur/transports/{$transport->id}", ['transport_type' => 'plane', 'distance_km' => 1])->assertForbidden();
        $this->actingAs($this->distributor)->get("/distributeur/transports/{$transport->id}")->assertOk();
        $this->actingAs(User::factory()->producer()->create())->get("/producteur/transports/{$transport->id}")->assertForbidden();
    }

    public function test_admin_reads_the_whole_chain(): void
    {
        $this->buildOliveOilJourney();
        $admin = User::factory()->admin()->create();

        foreach (['/admin/lots', "/admin/lots/{$this->oilLot->id}", '/admin/transformations', "/admin/transformations/{$this->transformation->id}", '/admin/transports', '/admin/distributions', "/admin/distributions/{$this->distribution->id}", '/admin'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $this->actingAs($admin)->post("/distributeur/distributions/{$this->distribution->id}/ventes", ['quantity' => 1])->assertForbidden();
    }
}
