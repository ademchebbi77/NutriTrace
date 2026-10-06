<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{UserRole, string}>
     */
    public static function roleDashboards(): array
    {
        return [
            'admin' => [UserRole::ADMIN, '/admin'],
            'producer' => [UserRole::PRODUCTEUR, '/producteur'],
            'transformer' => [UserRole::TRANSFORMATEUR, '/transformateur'],
            'distributor' => [UserRole::DISTRIBUTEUR, '/distributeur'],
            'consumer' => [UserRole::CONSOMMATEUR, '/consommateur'],
        ];
    }

    #[DataProvider('roleDashboards')]
    public function test_dashboard_redirects_each_role_to_its_own_area(UserRole $role, string $path): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user)->get('/dashboard')->assertRedirect($path);
        $this->actingAs($user)->get($path)->assertOk();
    }

    #[DataProvider('roleDashboards')]
    public function test_a_role_cannot_open_the_areas_of_other_roles(UserRole $role, string $ownPath): void
    {
        $user = $this->userWithRole($role);

        foreach (self::roleDashboards() as [, $path]) {
            if ($path !== $ownPath) {
                $this->actingAs($user)->get($path)->assertForbidden();
            }
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/producteur')->assertRedirect('/login');
    }

    public function test_unverified_users_must_verify_their_email_first(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/consommateur')->assertRedirect('/verify-email');
    }

    public function test_sidebar_only_shows_entries_of_the_logged_in_role(): void
    {
        $this->actingAs($this->userWithRole(UserRole::PRODUCTEUR))
            ->get('/producteur')
            ->assertSee(__('menu.productions'))
            ->assertDontSee(__('menu.pending_accounts'))
            ->assertDontSee(__('menu.qr_labels'));

        $this->actingAs($this->userWithRole(UserRole::ADMIN))
            ->get('/admin')
            ->assertSee(__('menu.pending_accounts'))
            ->assertDontSee(__('menu.productions'));
    }

    public function test_only_professionals_can_edit_an_organization(): void
    {
        $this->actingAs($this->userWithRole(UserRole::CONSOMMATEUR))->get('/organisation')->assertForbidden();
        $this->actingAs($this->userWithRole(UserRole::DISTRIBUTEUR))->get('/organisation')->assertOk();
    }

    public function test_a_professional_can_update_their_organization(): void
    {
        $user = $this->userWithRole(UserRole::PRODUCTEUR);

        $this->actingAs($user)->patch('/organisation', [
            'name' => 'Domaine Chaâl',
            'city' => 'Sfax',
            'latitude' => 34.7406,
            'longitude' => 10.7603,
            'is_verified' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect('/organisation');

        $organization = $user->organization->fresh();

        $this->assertSame('Domaine Chaâl', $organization->name);
        $this->assertEqualsWithDelta(34.7406, $organization->latitude, 0.0001);
        // Verification can only be granted by an admin.
        $this->assertFalse($organization->is_verified);
    }

    private function userWithRole(UserRole $role): User
    {
        return $role->isProfessional()
            ? User::factory()->professional($role)->create()
            : User::factory()->create(['role' => $role]);
    }
}
