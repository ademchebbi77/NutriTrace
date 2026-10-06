<?php

namespace Tests\Feature;

use App\Enums\LotStatus;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Review;
use App\Models\TraceabilityEvent;
use App\Models\User;
use App\Services\Traceability\ChainVerifier;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\VolumeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The demo data is produced by the real services: it must stay coherent.
 */
class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeded_demo_is_complete_and_consistent(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(20, Product::count());
        $this->assertGreaterThanOrEqual(55, Lot::count());
        $this->assertGreaterThan(300, TraceabilityEvent::count());
        $this->assertGreaterThan(40, Review::count());

        $verifier = app(ChainVerifier::class);

        foreach (Lot::with('environmentalImpact')->get() as $lot) {
            $this->assertTrue($verifier->verify($lot)->valid, "Broken chain on {$lot->lot_number}");
            $this->assertGreaterThanOrEqual(0, $lot->quantity, "Negative stock on {$lot->lot_number}");
            $this->assertLessThanOrEqual($lot->initial_quantity, $lot->quantity);
            $this->assertNotNull($lot->trust_score, "No transparency score on {$lot->lot_number}");

            if ($lot->quantity === 0.0) {
                $this->assertSame(LotStatus::SOLD_OUT, $lot->status, "Empty lot {$lot->lot_number} should be sold out");
            }
        }

        // No event is dated in the future.
        $this->assertSame(0, TraceabilityEvent::where('occurred_at', '>', now())->count());

        // Several grades appear, so the catalog filters have something to show.
        $grades = Lot::with('environmentalImpact')->get()->map(fn (Lot $lot) => $lot->environmentalImpact?->grade)->filter()->unique();
        $this->assertGreaterThanOrEqual(3, $grades->count());

        // Running the volume seeder again adds nothing.
        $users = User::count();
        $lots = Lot::count();
        $this->seed(VolumeSeeder::class);
        $this->assertSame($users, User::count());
        $this->assertSame($lots, Lot::count());

        // Every seeded page of the public site still renders.
        $this->get('/produits')->assertOk();
        $this->get('/trace/'.Lot::publiclyVisible()->latest('id')->first()->public_token)->assertOk();
    }
}
