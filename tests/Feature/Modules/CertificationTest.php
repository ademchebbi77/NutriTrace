<?php

namespace Tests\Feature\Modules;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsJourneys;
use Tests\TestCase;

class CertificationTest extends TestCase
{
    use BuildsJourneys, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->createActors();
    }

    public function test_a_producer_submits_a_certification_with_a_private_proof(): void
    {
        $product = Product::factory()->create(['created_by' => $this->producer->id]);

        $this->actingAs($this->producer)->get('/producteur/certifications/create')->assertOk()->assertSee($product->name);

        $this->actingAs($this->producer)->post('/producteur/certifications', $this->payload([
            'target' => "product:{$product->id}",
            'document' => UploadedFile::fake()->create('certificat.pdf', 200, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $certification = Certification::firstOrFail();

        $this->assertSame(CertificationStatus::PENDING, $certification->status);
        $this->assertTrue($certification->certifiable->is($product));
        $this->assertFalse($certification->isValid());
        Storage::disk('local')->assertExists($certification->document_path);
        // Not on the public disk: no direct URL.
        Storage::disk('public')->assertMissing($certification->document_path);

        $this->actingAs($this->producer)->get('/producteur/certifications')->assertOk()->assertSee('Agriculture biologique');
        $this->actingAs($this->producer)->get("/producteur/certifications/{$certification->id}")->assertOk();
        $this->actingAs($this->producer)->get("/certifications/{$certification->id}/document")->assertOk();
    }

    public function test_a_certification_can_cover_a_lot_made_or_held_by_its_owner(): void
    {
        $lot = $this->createOliveLot();

        $this->actingAs($this->producer)->get('/producteur/certifications/create')->assertOk()->assertSee($lot->lot_number);

        $this->actingAs($this->producer)->post('/producteur/certifications', $this->payload(['target' => "lot:{$lot->id}"]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Certification::firstOrFail()->certifiable->is($lot));

        // A transformer who never handled the lot cannot certify it.
        $this->actingAs($this->transformer)->post('/transformateur/certifications', $this->payload(['target' => "lot:{$lot->id}"]))
            ->assertSessionHasErrors('target');

        $this->assertSame(1, Certification::count());
    }

    public function test_the_proof_is_only_served_to_its_owner_and_admins(): void
    {
        $certification = $this->storedCertification();

        $this->get("/certifications/{$certification->id}/document")->assertRedirect('/login');
        $this->actingAs($this->transformer)->get("/certifications/{$certification->id}/document")->assertForbidden();
        $this->actingAs(User::factory()->create())->get("/certifications/{$certification->id}/document")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get("/certifications/{$certification->id}/document")->assertOk();
    }

    public function test_uploads_and_targets_are_validated(): void
    {
        $foreign = Product::factory()->create();
        $mine = Product::factory()->create(['created_by' => $this->producer->id]);

        $this->actingAs($this->producer)->post('/producteur/certifications', $this->payload(['target' => "product:{$foreign->id}"]))
            ->assertSessionHasErrors('target');

        $this->actingAs($this->producer)->post('/producteur/certifications', $this->payload([
            'target' => "product:{$mine->id}",
            'document' => UploadedFile::fake()->create('script.php', 10, 'text/x-php'),
            'expiration_date' => '2020-01-01',
        ]))->assertSessionHasErrors(['document', 'expiration_date']);

        $this->assertSame(0, Certification::count());
    }

    public function test_admin_approves_or_rejects_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $first = $this->storedCertification();
        $second = $this->storedCertification();

        $this->actingAs($admin)->get('/admin/certifications')->assertOk()->assertSee($first->name);
        $this->actingAs($admin)->get("/admin/certifications/{$first->id}")->assertOk()->assertSee(__('certifications.admin.approve'));

        $this->actingAs($admin)->post("/admin/certifications/{$first->id}/approuver")->assertRedirect('/admin/certifications');
        $this->assertSame(CertificationStatus::VERIFIED, $first->fresh()->status);
        $this->assertTrue($first->fresh()->isValid());

        $this->actingAs($admin)->post("/admin/certifications/{$second->id}/refuser", ['rejection_reason' => ''])->assertSessionHasErrors('rejection_reason');
        $this->actingAs($admin)->post("/admin/certifications/{$second->id}/refuser", ['rejection_reason' => 'Numéro inconnu de l\'organisme.']);
        $this->assertSame(CertificationStatus::REJECTED, $second->fresh()->status);

        $this->assertDatabaseHas('audit_logs', ['action' => 'certification.approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'certification.rejected']);

        // Reviewed once; and never by a non-admin.
        $this->actingAs($admin)->post("/admin/certifications/{$first->id}/refuser", ['rejection_reason' => 'Changement d\'avis.'])->assertForbidden();
        $this->actingAs($this->producer)->post("/admin/certifications/{$second->id}/approuver")->assertForbidden();
    }

    public function test_a_past_certificate_is_recognised_but_not_valid(): void
    {
        $certification = $this->storedCertification(['issue_date' => '2020-01-01', 'expiration_date' => '2021-01-01']);

        $this->actingAs(User::factory()->admin()->create())->post("/admin/certifications/{$certification->id}/approuver");

        $this->assertSame(CertificationStatus::EXPIRED, $certification->fresh()->status);
    }

    public function test_correcting_a_rejected_certification_sends_it_back_for_review(): void
    {
        $certification = $this->storedCertification();
        $certification->forceFill(['status' => CertificationStatus::REJECTED, 'rejection_reason' => 'Illisible'])->save();

        $this->actingAs($this->producer)->get("/producteur/certifications/{$certification->id}/edit")->assertOk();
        $this->actingAs($this->producer)->put("/producteur/certifications/{$certification->id}", $this->payload(['certificate_number' => 'TN-BIO-999']))
            ->assertSessionHasNoErrors();

        $certification->refresh();
        $this->assertSame(CertificationStatus::PENDING, $certification->status);
        $this->assertNull($certification->rejection_reason);
        $this->assertSame('TN-BIO-999', $certification->certificate_number);

        // A verified certificate is frozen.
        $certification->forceFill(['status' => CertificationStatus::VERIFIED])->save();
        $this->actingAs($this->producer)->put("/producteur/certifications/{$certification->id}", $this->payload())->assertForbidden();

        // Others cannot touch it.
        $other = User::factory()->producer()->create();
        $this->actingAs($other)->get("/producteur/certifications/{$certification->id}")->assertForbidden();
        $this->actingAs($other)->delete("/producteur/certifications/{$certification->id}")->assertForbidden();

        $this->actingAs($this->producer)->delete("/producteur/certifications/{$certification->id}")->assertRedirect('/producteur/certifications');
        $this->assertModelMissing($certification);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Agriculture biologique',
            'type' => 'BIO',
            'issuing_organization' => 'Ecocert',
            'certificate_number' => 'TN-BIO-001',
            'issue_date' => now()->subYear()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function storedCertification(array $overrides = []): Certification
    {
        $path = UploadedFile::fake()->create('preuve.pdf', 50, 'application/pdf')->store('certifications', 'local');

        return Certification::factory()->bio()
            ->for(Product::factory()->create(['created_by' => $this->producer->id]), 'certifiable')
            ->create($overrides + ['owner_id' => $this->producer->id, 'document_path' => $path]);
    }
}
