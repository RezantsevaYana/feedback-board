<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Password lockout per email (docs/ROLES_PLAN.md, stage 2): 5 wrong passwords in 10 minutes
 * lock password login for this address for 15 minutes, even with the right password.
 * A password reset by letter lifts the lock early.
 *
 * Works the same for unknown addresses, so the lock can't be used to probe for accounts.
 * The per-IP `throttle:login` stays on top of it against one machine trying many addresses.
 */
class LoginLockout
{
    public const MAX_ATTEMPTS = 5;

    public const WINDOW_SECONDS = 10 * 60;

    public const LOCK_MINUTES = 15;

    public function locked(string $email): bool
    {
        return Cache::has($this->lockKey($email));
    }

    /** A wrong password; the fifth one in the window turns into the lock. */
    public function failed(string $email): void
    {
        RateLimiter::hit($this->attemptsKey($email), self::WINDOW_SECONDS);

        if (RateLimiter::attempts($this->attemptsKey($email)) >= self::MAX_ATTEMPTS) {
            Cache::put($this->lockKey($email), true, now()->addMinutes(self::LOCK_MINUTES));
            RateLimiter::clear($this->attemptsKey($email));
        }
    }

    /** A successful login or a new password: the count starts over and the lock goes away. */
    public function clear(string $email): void
    {
        RateLimiter::clear($this->attemptsKey($email));
        Cache::forget($this->lockKey($email));
    }

    private function attemptsKey(string $email): string
    {
        return 'login-failures:'.sha1(Str::lower(trim($email)));
    }

    private function lockKey(string $email): string
    {
        return 'login-lock:'.sha1(Str::lower(trim($email)));
    }
}