<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BotGuardService;
use App\Services\SecurityAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    private const MAX_FAILED_ATTEMPTS = 5;

    private const LOCKOUT_MINUTES = 15;

    public function __construct(
        private SecurityAuditService $audit,
        private BotGuardService $botGuard,
    ) {}

    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('orders.index');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => 'Введите email',
            'email.email' => 'Введите корректный email',
            'password.required' => 'Введите пароль',
        ]);

        $email = strtolower(trim($credentials['email']));
        $this->ensureNotLockedOut($email, $request->ip());

        $credentials['is_active'] = true;

        $pendingUser = User::query()->where('email', $email)->first();
        if ($pendingUser && $this->botGuard->isLocked((int) $pendingUser->user_id)) {
            $sec = $this->botGuard->lockRemainingSeconds((int) $pendingUser->user_id);
            $mins = max(1, (int) ceil($sec / 60));
            throw ValidationException::withMessages([
                'email' => ["Аккаунт временно заблокирован после подозрительной активности. Повторите через ~{$mins} мин. или обратитесь к руководителю."],
            ]);
        }
        if ($pendingUser && $pendingUser->is_blacklisted) {
            throw ValidationException::withMessages([
                'email' => ['Учётная запись в чёрном списке.'],
            ]);
        }

        if (Auth::attempt($credentials, false)) {
            $request->session()->regenerate();
            $request->session()->put('auth_started_at', time());
            $this->clearFailedAttempts($email, $request->ip());

            $user = Auth::user();
            $this->audit->log('login_success', $user, 'user', (string) $user->user_id, [
                'email' => $user->email,
            ], $request);

            $request->session()->forget('two_factor_passed');

            if ($user->requiresTwoFactor()) {
                if (! $user->hasTwoFactorEnabled()) {
                    return redirect()->route('two-factor.setup');
                }

                return redirect()->route('two-factor.challenge');
            }

            $request->session()->put('two_factor_passed', true);

            return redirect()->intended(route('orders.index'));
        }

        $this->hitFailedAttempt($email, $request->ip());
        $this->audit->log('login_failed', null, null, null, [
            'email' => $email,
        ], $request);

        throw ValidationException::withMessages([
            'email' => ['Неверный email или пароль'],
        ]);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $this->audit->log('logout', $user, 'user', (string) $user->user_id, null, $request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $hub = rtrim((string) config('services.hub.public_url'), '/');

        return $hub !== ''
            ? redirect()->away($hub.'/login')
            : redirect()->away(hub_login_url());
    }

    private function lockKey(string $email, ?string $ip = null): string
    {
        // Без IP: смена VPN/NAT не должна оставлять «залипший» lock и не блокировать коллег.
        return 'login_lock:'.sha1(strtolower($email));
    }

    private function failKey(string $email, ?string $ip = null): string
    {
        return 'login_fail:'.sha1(strtolower($email));
    }

    private function ensureNotLockedOut(string $email, ?string $ip): void
    {
        if (Cache::has($this->lockKey($email, $ip))) {
            throw ValidationException::withMessages([
                'email' => ['Слишком много неудачных попыток. Попробуйте через '.self::LOCKOUT_MINUTES.' минут.'],
            ]);
        }
    }

    private function hitFailedAttempt(string $email, ?string $ip): void
    {
        $key = $this->failKey($email, $ip);
        $fails = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $fails, now()->addMinutes(self::LOCKOUT_MINUTES));

        if ($fails >= self::MAX_FAILED_ATTEMPTS) {
            Cache::put($this->lockKey($email, $ip), true, now()->addMinutes(self::LOCKOUT_MINUTES));
            Cache::forget($key);
        }
    }

    private function clearFailedAttempts(string $email, ?string $ip): void
    {
        Cache::forget($this->failKey($email, $ip));
        Cache::forget($this->lockKey($email, $ip));
    }
}
