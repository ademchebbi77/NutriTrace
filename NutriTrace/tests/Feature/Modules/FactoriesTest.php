<?php

namespace Tests\Feature\Modules;

use App\Enums\EventType;
use App\Models\Certification;
use App\Models\Distribution;
use App\Models\LotTransfer;
use App\Models\Report;
use App\Models\Review;
use App\Models\Transformation;
use App\Models\Transport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every module can be exercised on its own through its factory.
 */
class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_factories_create_consistent_records(): void
    {
        $transport = Transport::factory()->create();
        $this->assertSame($transport->lot->current_holder_id, $transport->shipper_id);
        $this->assertSame(EventType::TRANSPORT, $transport->lot->events()->latest('id')->first()->event_type);

        $transfer = LotTransfer::factory()->create();
        $this->assertTrue($transfer->isPending());
        $this->assertSame($transfer->lot->quantity, $transfer->quantity);

        $transformation = Transformation::factory()->create();
        $this->assertTrue($transformation->outputLot->isHeldBy($transformation->transformer));
        $this->assertSame(1, $transformation->outputLot->events()->where('event_type', EventType::TRANSFORMATION)->count());

        $distribution = Distribution::factory()->create();
        $this->assertTrue($distribution->isPending());
        $this->assertSame(EventType::DISTRIBUTION, $distribution->lot->events()->latest('id')->first()->event_type);

        $this->assertFalse(Certification::factory()->create()->isValid());
        $this->assertTrue(Certification::factory()->verified()->create()->isValid());
        $this->assertFalse(Certification::factory()->expired()->create()->isValid());
        $this->assertFalse(Certification::factory()->verified()->withoutProof()->create()->hasProof());

        $this->assertBetween(Review::factory()->create()->rating);
        $this->assertTrue(Report::factory()->create()->status->isOpen());
    }

    private function assertBetween(int $rating): void
    {
        $this->assertGreaterThanOrEqual(1, $rating);
        $this->assertLessThanOrEqual(5, $rating);
    }
}
