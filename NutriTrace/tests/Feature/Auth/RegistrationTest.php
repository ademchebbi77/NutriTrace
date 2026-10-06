<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee(__('roles.PRODUCTEUR'))
            ->assertDontSee(__('roles.ADMIN'));
    }

    public function test_consumers_can_register_and_are_active_immediately(): void
    {
        Notification::fake();

        $response = $this->post('/register', $this->payload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::firstWhere('email', 'test@example.com');

        $this->assertSame(UserRole::CONSOMMATEUR, $user->role);
        $this->assertSame(AccountStatus::APPROVED, $user->account_status);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->organization);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_professionals_register_as_pending_with_an_organization(): void
    {
        $this->post('/register', $this->payload([
            'role' => 'PRODUCTEUR',
            'organization_name' => 'Domaine Test',
            'organization_city' => 'Sfax',
            'registration_number' => '1234567A',
        ]))->assertRedirect(route('dashboard', absolute: false));

        $user = User::firstWhere('email', 'test@example.com');

        $this->assertSame(UserRole::PRODUCTEUR, $user->role);
        $this->assertSame(AccountStatus::PENDING, $user->account_status);
        $this->assertFalse($user->is_active);
        $this->assertSame('Domaine Test', $user->organization->name);
        $this->assertFalse($user->organization->is_verified);
    }

    public function test_professionals_must_provide_their_organization(): void
    {
        $this->post('/register', $this->payload(['role' => 'DISTRIBUTEUR']))
            ->assertSessionHasErrors(['organization_name', 'organization_city']);

        $this->assertGuest();
    }

    public function test_nobody_can_register_as_admin(): void
    {
        $this->post('/register', $this->payload(['role' => 'ADMIN']))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_validation_messages_are_in_french(): void
    {
        $this->post('/register', $this->payload(['email' => '']))
            ->assertSessionHasErrors(['email' => 'Le champ adresse e-mail est obligatoire.']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'CONSOMMATEUR',
        ];
    }
}
