<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the session policy exposed in Admin → Settings → Users:
 * - session_idle_timeout:  max inactivity gap in minutes (0 = disabled)
 * - session_lifetime:      max session age in minutes (0 = disabled)
 * - remember_me_duration:  max age of a "remember me" login in minutes (0 = disabled)
 *
 * Activity is tracked in an encrypted, long-lived cookie mirrored into the
 * session so the idle check survives Laravel's remember-me recaller silently
 * resurrecting an expired session.
 */
class EnforceSessionPolicy
{
    public const POLICY_COOKIE = 'session_policy';

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $state = $this->read($request);

            if ($this->sessionExpired($state)) {
                $this->expireSession($request);

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Session expired.'], 401);
                }

                return redirect()->route('login')->withErrors([
                    'general' => 'Your session has expired. Please sign in again.',
                ]);
            }

            $this->touch($request, $state);
        }

        return $next($request);
    }

    /**
     * @return array{last_request_at:int, started_at:int, remembered_at:int, via_remember:bool}
     */
    protected function read(Request $request): array
    {
        $now = now()->timestamp;

        [$cookieLast, $cookieRemembered] = $this->parseCookie($request);

        $session = $request->hasSession() ? $request->session() : null;

        return [
            'last_request_at' => (int) ($cookieLast ?: $session?->get('last_request_at') ?: $now),
            'started_at' => (int) ($session?->get('session_started_at') ?: $now),
            'remembered_at' => (int) $cookieRemembered,
            'via_remember' => Auth::viaRemember(),
        ];
    }

    /**
     * @return array{0:int, 1:int}
     */
    protected function parseCookie(Request $request): array
    {
        $parts = array_map('intval', explode('|', (string) $request->cookie(self::POLICY_COOKIE, '')));

        return [$parts[0] ?? 0, $parts[1] ?? 0];
    }

    /**
     * @param  array{last_request_at:int, started_at:int, remembered_at:int, via_remember:bool}  $state
     */
    protected function touch(Request $request, array $state): void
    {
        $now = now()->timestamp;

        if ($request->hasSession()) {
            $session = $request->session();

            if (! $session->has('session_started_at')) {
                $session->put('session_started_at', $state['started_at']);
            }

            $session->put('last_request_at', $now);
        }

        $this->queuePolicyCookie($now, $state['remembered_at']);
    }

    /**
     * @param  array{last_request_at:int, started_at:int, remembered_at:int, via_remember:bool}  $state
     */
    protected function sessionExpired(array $state): bool
    {
        // A recaller-resurrected session with no policy record cannot be
        // verified — force a fresh login instead of trusting the recaller.
        if ($state['via_remember'] && $state['remembered_at'] <= 0) {
            return true;
        }

        $now = now()->timestamp;

        $idleMinutes = (int) (SystemSetting::get('session_idle_timeout') ?? 30);

        if ($idleMinutes > 0 && ($now - $state['last_request_at']) > $idleMinutes * 60) {
            return true;
        }

        if ($state['remembered_at'] > 0) {
            $rememberMinutes = (int) (SystemSetting::get('remember_me_duration') ?? 43200);

            return $rememberMinutes > 0 && ($now - $state['remembered_at']) > $rememberMinutes * 60;
        }

        $lifetimeMinutes = (int) (SystemSetting::get('session_lifetime') ?? 1440);

        return $lifetimeMinutes > 0 && ($now - $state['started_at']) > $lifetimeMinutes * 60;
    }

    protected function expireSession(Request $request): void
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        cookie()->queue(cookie()->forget(self::POLICY_COOKIE));
    }

    protected function queuePolicyCookie(int $lastRequestAt, int $rememberedAt): void
    {
        $minutes = max(1, (int) (SystemSetting::get('remember_me_duration') ?? 43200));

        cookie()->queue(cookie(self::POLICY_COOKIE, $lastRequestAt.'|'.$rememberedAt, $minutes));
    }
}
