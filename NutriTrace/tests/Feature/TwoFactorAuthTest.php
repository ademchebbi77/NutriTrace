<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->google2fa = new Google2FA;
    }

    /**
     * A user who finished the setup: secret, recovery codes and confirmation date.
     *
     * @param  list<string>  $recoveryCodes
     */
    private function userWithTwoFactor(array $recoveryCodes = ['AAAA111111', 'BBBB222222']): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => encrypt($this->google2fa->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    private function currentCode(User $user): string
    {
        return $this->google2fa->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));
    }

    public function test_the_setup_page_creates_a_secret_once_and_shows_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile/two-factor-authentication')->assertOk();
        $secret = $user->fresh()->two_factor_secret;
        $this->assertNotNull($secret);

        // Second visit: the same secret is kept (the QR code stays valid).
        $this->actingAs($user)->get('/profile/two-factor-authentication')
            ->assertOk()
            ->assertSee(decrypt($secret));
        $this->assertSame(decrypt($secret), decrypt($user->fresh()->two_factor_secret));
    }

    public function test_two_factor_is_enabled_with_a_valid_code_only(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile/two-factor-authentication');

        $this->actingAs($user)->post('/profile/two-factor-authentication', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->two_factor_confirmed_at);

        $this->actingAs($user)->post('/profile/two-factor-authentication', ['code' => $this->currentCode($user)])
            ->assertRedirect(route('two-factor.show'))
            ->assertSessionHas('recovery_codes');

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertCount(8, json_decode(decrypt($user->two_factor_recovery_codes)));
    }

    public function test_recovery_codes_are_shown_and_regenerated_with_the_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile/two-factor-recovery-codes')
            ->assertRedirect(route('two-factor.show'));

        $user = $this->userWithTwoFactor(['AAAA111111', 'BBBB222222']);
        $this->actingAs($user)->get('/profile/two-factor-recovery-codes')
            ->assertOk()
            ->assertSee('AAAA111111');

        $this->actingAs($user)->post('/profile/two-factor-recovery-codes', ['password' => 'wrong'])
            ->assertSessionHasErrors('password');

        $this->actingAs($user)->post('/profile/two-factor-recovery-codes', ['password' => 'password'])
            ->assertRedirect(route('two-factor.recovery-codes'));
        $codes = json_decode(decrypt($user->fresh()->two_factor_recovery_codes));
        $this->assertCount(8, $codes);
        $this->assertNotContains('AAAA111111', $codes);
    }

    public function test_two_factor_is_disabled_with_the_password(): void
    {
        $user = $this->userWithTwoFactor();

        $this->actingAs($user)->delete('/profile/two-factor-authentication', ['password' => 'wrong'])
            ->assertSessionHasErrors('password');
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        $this->actingAs($user)->delete('/profile/two-factor-authentication', ['password' => 'password'])
            ->assertRedirect(route('two-factor.show'));
        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_pages_wait_for_the_code_when_two_factor_is_enabled(): void
    {
        $user = $this->userWithTwoFactor();

        // Not verified in this session: every page leads to the challenge, which itself opens.
        $this->actingAs($user)->get('/consommateur')->assertRedirect(route('two-factor.challenge'));
        $this->actingAs($user)->get('/two-factor-challenge')->assertOk();

        // Verified in this session: pages open normally.
        $this->actingAs($user)->withSession(['two_factor_verified' => true])->get('/consommateur')->assertOk();

        // Without two-factor, nothing changes.
        $this->actingAs(User::factory()->create())->get('/consommateur')->assertOk();
    }

    public function test_the_challenge_accepts_the_current_code(): void
    {
        $user = $this->userWithTwoFactor();

        $this->actingAs($user)->post('/two-factor-challenge', ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->actingAs($user)->post('/two-factor-challenge', ['code' => $this->currentCode($user)])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('two_factor_verified', true);
    }

    public function test_a_recovery_code_works_once(): void
    {
        $user = $this->userWithTwoFactor(['AAAA111111', 'BBBB222222']);

        // Accepted in lower case too, then removed from the list.
        $this->actingAs($user)->post('/two-factor-challenge', ['code' => 'aaaa111111'])
            ->assertSessionHas('two_factor_verified', true);
        $this->assertSame(['BBBB222222'], json_decode(decrypt($user->fresh()->two_factor_recovery_codes)));

        $this->actingAs($user)->post('/two-factor-challenge', ['code' => 'AAAA111111'])
            ->assertSessionHasErrors('code');
    }

    public function test_the_challenge_is_refused_when_two_factor_is_not_enabled(): void
    {
        $this->actingAs(User::factory()->create())->post('/two-factor-challenge', ['code' => '123456'])
            ->assertSessionHasErrors('code');
    }
}
