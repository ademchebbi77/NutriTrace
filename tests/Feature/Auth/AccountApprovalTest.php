<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Notifications\AccountReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_professionals_can_log_in_but_not_use_the_back_office(): void
    {
        $user = User::factory()->producer()->pending()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);

        $this->get('/dashboard')->assertRedirect('/compte/statut');
        $this->get('/producteur')->assertRedirect('/compte/statut');
        $this->get('/organisation')->assertRedirect('/compte/statut');

        $this->get('/compte/statut')
            ->assertOk()
            ->assertSee(__('account.status.pending_heading'));
    }

    public function test_rejected_users_see_the_reason(): void
    {
        $user = User::factory()->distributor()->create([
            'account_status' => AccountStatus::REJECTED,
            'is_active' => false,
            'rejection_reason' => 'Matricule fiscal invalide.',
        ]);

        $this->actingAs($user)->get('/distributeur')->assertRedirect('/compte/statut');

        $this->actingAs($user)->get('/compte/statut')
            ->assertSee(__('account.status.rejected_heading'))
            ->assertSee('Matricule fiscal invalide.');
    }

    public function test_deactivated_users_cannot_use_the_back_office(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->get('/consommateur')->assertRedirect('/compte/statut');

        $this->actingAs($user)->get('/compte/statut')
            ->assertSee(__('account.status.inactive_heading'));
    }

    public function test_active_users_are_sent_away_from_the_status_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/compte/statut')
            ->assertRedirect('/dashboard');
    }

    public function test_admin_can_approve_a_pending_account(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->transformer()->pending()->create();

        $this->actingAs($admin)->get('/admin/comptes-en-attente')
            ->assertOk()
            ->assertSee($user->organization->name);

        $this->actingAs($admin)
            ->post("/admin/comptes-en-attente/{$user->id}/approuver")
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertSame(AccountStatus::APPROVED, $user->account_status);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->reviewer->is($admin));
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.approved', 'user_id' => $admin->id, 'auditable_id' => $user->id]);
        Notification::assertSentTo($user, AccountReviewed::class);

        $this->actingAs($user)->get('/transformateur')->assertOk();
    }

    public function test_admin_can_reject_a_pending_account_with_a_reason(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->producer()->pending()->create();

        $this->actingAs($admin)
            ->post("/admin/comptes-en-attente/{$user->id}/rejeter", ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($admin)
            ->post("/admin/comptes-en-attente/{$user->id}/rejeter", ['rejection_reason' => 'Documents manquants.'])
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertSame(AccountStatus::REJECTED, $user->account_status);
        $this->assertFalse($user->is_active);
        $this->assertSame('Documents manquants.', $user->rejection_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.rejected']);
    }

    public function test_an_account_cannot_be_reviewed_twice(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->producer()->create();

        $this->actingAs($admin)
            ->post("/admin/comptes-en-attente/{$user->id}/rejeter", ['rejection_reason' => 'Trop tard.'])
            ->assertForbidden();
    }

    public function test_non_admins_cannot_review_accounts(): void
    {
        $user = User::factory()->producer()->pending()->create();

        $this->actingAs(User::factory()->distributor()->create())
            ->post("/admin/comptes-en-attente/{$user->id}/approuver")
            ->assertForbidden();

        $this->assertTrue($user->fresh()->isPending());
    }

    public function test_admin_can_deactivate_and_reactivate_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->patch("/admin/utilisateurs/{$user->id}/activation");
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)->patch("/admin/utilisateurs/{$user->id}/activation");
        $this->assertTrue($user->fresh()->is_active);

        $this->assertDatabaseHas('audit_logs', ['action' => 'account.deactivated']);
    }

    public function test_admin_accounts_cannot_be_deactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin)->patch("/admin/utilisateurs/{$other->id}/activation")->assertForbidden();
    }

    public function test_admin_can_verify_an_organization(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = User::factory()->producer()->create()->organization;

        $this->actingAs($admin)->get('/admin/utilisateurs')->assertOk()->assertSee($organization->name);

        $this->actingAs($admin)->patch("/admin/organisations/{$organization->id}/verification");

        $organization->refresh();

        $this->assertTrue($organization->is_verified);
        $this->assertTrue($organization->verifier->is($admin));
    }

    public function test_make_admin_command_creates_an_active_admin(): void
    {
        $this->artisan('make:admin', [
            '--name' => 'Admin Test',
            '--email' => 'root@nutritrace.test',
            '--password' => 'secret-password',
        ])->assertSuccessful();

        $admin = User::firstWhere('email', 'root@nutritrace.test');

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->canAccessBackOffice());
        $this->assertTrue($admin->hasVerifiedEmail());

        $this->artisan('make:admin', [
            '--name' => 'Admin Test',
            '--email' => 'root@nutritrace.test',
            '--password' => 'secret-password',
        ])->assertFailed();
    }
}
