<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Show 2FA setup page
     */
    public function show()
    {
        $user = Auth::user();
        
        // Generate secret if not exists
        if (!$user->two_factor_secret) {
            $secret = $this->google2fa->generateSecretKey();
            $user->two_factor_secret = encrypt($secret);
            $user->save();
        } else {
            $secret = decrypt($user->two_factor_secret);
        }

        // Generate QR Code URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return view('profile.two-factor-authentication', [
            'qrCodeUrl' => $qrCodeUrl,
            'secret' => $secret,
            'enabled' => !is_null($user->two_factor_confirmed_at)
        ]);
    }

    /**
     * Enable 2FA
     */
    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|numeric|digits:6'
        ]);

        $user = Auth::user();
        $secret = decrypt($user->two_factor_secret);

        // Verify the code
        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return back()->withErrors(['code' => 'Code de vérification invalide.']);
        }

        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();
        
        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $user->two_factor_confirmed_at = now();
        $user->save();

        return redirect()->route('two-factor.show')
            ->with('recovery_codes', $recoveryCodes)
            ->with('status', 'Authentification à deux facteurs activée avec succès!');
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password'
        ]);

        $user = Auth::user();
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return redirect()->route('two-factor.show')
            ->with('status', 'Authentification à deux facteurs désactivée.');
    }

    /**
     * Show recovery codes
     */
    public function showRecoveryCodes()
    {
        $user = Auth::user();

        if (!$user->two_factor_confirmed_at) {
            return redirect()->route('two-factor.show');
        }

        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes));

        return view('profile.two-factor-recovery-codes', [
            'recoveryCodes' => $recoveryCodes
        ]);
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password'
        ]);

        $user = Auth::user();
        $recoveryCodes = $this->generateRecoveryCodes();
        
        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $user->save();

        return redirect()->route('two-factor.recovery-codes')
            ->with('recovery_codes', $recoveryCodes)
            ->with('status', 'Nouveaux codes de récupération générés.');
    }

    /**
     * Generate recovery codes
     */
    protected function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(8))), 0, 10));
        }
        return $codes;
    }

    /**
     * Verify 2FA challenge
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $user = Auth::user();

        if (!$user->two_factor_confirmed_at) {
            return back()->withErrors(['code' => '2FA n\'est pas activé.']);
        }

        $secret = decrypt($user->two_factor_secret);

        // Try verifying with TOTP code
        $valid = $this->google2fa->verifyKey($secret, $request->code);

        // If TOTP fails, try recovery codes
        if (!$valid) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            
            if (in_array(strtoupper($request->code), $recoveryCodes)) {
                // Remove used recovery code
                $recoveryCodes = array_diff($recoveryCodes, [strtoupper($request->code)]);
                $user->two_factor_recovery_codes = encrypt(json_encode(array_values($recoveryCodes)));
                $user->save();
                
                $valid = true;
            }
        }

        if (!$valid) {
            return back()->withErrors(['code' => 'Code invalide.']);
        }

        // Mark 2FA as verified in session
        $request->session()->put('two_factor_verified', true);

        return redirect()->intended(route('dashboard'));
    }
}
