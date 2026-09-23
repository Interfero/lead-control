<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SecurityAuditService;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(
        private SecurityAuditService $audit,
    ) {}

    public function challenge()
    {
        $user = Auth::user();
        if (! $user || ! $user->requiresTwoFactor()) {
            return redirect()->route('orders.index');
        }
        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }
        if (session('two_factor_passed')) {
            return redirect()->route('orders.index');
        }

        return view('auth.two-factor-challenge');
    }

    public function verifyChallenge(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ], [
            'code.required' => 'Введите код из приложения',
        ]);

        $user = Auth::user();
        if (! $user || ! $user->two_factor_secret) {
            return redirect()->away(hub_login_url());
        }

        if (! Totp::verify($user->two_factor_secret, $request->input('code'))) {
            $this->audit->log('two_factor_failed', $user, 'user', (string) $user->user_id, null, $request);
            throw ValidationException::withMessages([
                'code' => ['Неверный код. Попробуйте ещё раз.'],
            ]);
        }

        $request->session()->put('two_factor_passed', true);
        $this->audit->log('two_factor_passed', $user, 'user', (string) $user->user_id, null, $request);

        return redirect()->intended(route('orders.index'));
    }

    public function setup()
    {
        $user = Auth::user();
        if (! $user || ! $user->requiresTwoFactor()) {
            return redirect()->route('orders.index');
        }

        // Перепривязка: если уже включена — генерируем новый секрет (нужно подтвердить кодом)
        if (! $user->two_factor_secret || $user->two_factor_confirmed_at) {
            $secret = Totp::generateSecret();
            $user->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_confirmed_at' => null,
            ])->save();
            $request = request();
            $request->session()->forget('two_factor_passed');
        } else {
            $secret = $user->two_factor_secret;
        }

        $uri = Totp::provisioningUri($secret, $user->email ?? $user->user_name);

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'otpauthUri' => $uri,
            'qrUrl' => Totp::qrImageUrl($uri),
        ]);
    }

    public function confirmSetup(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = Auth::user();
        if (! $user?->two_factor_secret) {
            return redirect()->route('two-factor.setup');
        }

        if (! Totp::verify($user->two_factor_secret, $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => ['Неверный код. Проверьте время на телефоне и секрет.'],
            ]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $request->session()->put('two_factor_passed', true);
        $this->audit->log('two_factor_enabled', $user, 'user', (string) $user->user_id, null, $request);

        return redirect()->route('orders.index')->with('success', 'Двухфакторная аутентификация включена.');
    }

    public function disable(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasAnyRole(['developer', 'general_director'])) {
            abort(403);
        }

        // Отключить 2FA критичным ролям нельзя — только перевыпустить секрет через setup
        return back()->with('error', 'Для вашей роли 2FA обязательна. Можно только перепривязать приложение в настройках.');
    }
}
