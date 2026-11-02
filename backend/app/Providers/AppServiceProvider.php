<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Role-aware abilities on one board, see the table in docs/ROLES_PLAN.md §1.
        // triage = posts, statuses of cards, hiding, replies; configure = categories, columns, settings, team.
        $roleOn = fn (User $user, Project $project) => $user->roleOn($project);

        Gate::define('board.configure', fn (User $user, Project $project) => (bool) $roleOn($user, $project)?->canConfigureBoard());
        Gate::define('board.triage', fn (User $user, Project $project) => (bool) $roleOn($user, $project)?->canTriage());
        Gate::define('board.vote', fn (User $user, Project $project) => (bool) $roleOn($user, $project)?->canVote());
        Gate::define('board.submit', fn (User $user, Project $project) => (bool) $roleOn($user, $project)?->canSubmit());
        Gate::define('board.delete', fn (User $user, Project $project) => (bool) $roleOn($user, $project)?->canDeleteBoard());
        // the service's own admins: every board and account in /app/platform, granted by superadmin:grant
        Gate::define('platform.admin', fn (User $user) => $user->is_superadmin);

        $tooMany = fn (string $message) => fn () => response()->json(['message' => $message], 429);

        RateLimiter::for('posts', fn (Request $request) => Limit::perHour(10)->by($request->ip())
            ->response($tooMany('Слишком много предложений за час. Попробуйте позже.')));
        RateLimiter::for('votes', fn (Request $request) => Limit::perMinute(60)->by($request->ip())
            ->response($tooMany('Слишком много голосов подряд. Подождите минуту.')));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip())
            ->response($tooMany('Слишком много попыток входа. Подождите минуту.')));
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(5)->by($request->ip())
            ->response($tooMany('Слишком много регистраций с этого адреса. Попробуйте позже.')));
        RateLimiter::for('verify-email', fn (Request $request) => Limit::perMinutes(10, 6)->by($request->user()?->id ?: $request->ip())
            ->response($tooMany('Слишком много писем подряд. Подождите несколько минут.')));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinutes(10, 6)->by($request->ip())
            ->response($tooMany('Слишком много попыток. Подождите несколько минут.')));
        RateLimiter::for('access-requests', fn (Request $request) => Limit::perHour(5)->by($request->ip())
            ->response($tooMany('Слишком много запросов доступа. Попробуйте позже.')));
        // the current password is checked here too, so guessing it from an open session is limited
        RateLimiter::for('password-change', fn (Request $request) => Limit::perMinutes(10, 5)->by($request->user()?->id ?: $request->ip())
            ->response($tooMany('Слишком много попыток. Подождите несколько минут.')));

        // The letter links to the SPA page, not the API: /app/verify-email/{id}/{hash}?expires=…&signature=…
        // The page posts the same path and query to the API, which checks the signature (signed:relative).
        VerifyEmail::createUrlUsing(function (User $user) {
            $signed = URL::temporarySignedRoute('verification.verify', now()->addDay(), [
                'user' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ], absolute: false);

            return rtrim(config('app.frontend_url'), '/').'/app/verify-email'.Str::after($signed, '/api/admin/email/verify');
        });
        VerifyEmail::toMailUsing(fn (User $user, string $url) => (new MailMessage)
            ->subject('Подтвердите почту')
            ->markdown('mail.verify-email', ['name' => $user->name, 'url' => $url]));

        // The reset letter links to the SPA page as well; the page posts the token, email and new password.
        $resetUrl = fn (User $user, string $token) => rtrim(config('app.frontend_url'), '/')
            .'/app/reset-password/'.$token.'?'.http_build_query(['email' => $user->getEmailForPasswordReset()]);
        ResetPassword::createUrlUsing($resetUrl);
        ResetPassword::toMailUsing(fn (User $user, string $token) => (new MailMessage)
            ->subject('Сброс пароля')
            ->markdown('mail.reset-password', [
                'name' => $user->name,
                'url' => $resetUrl($user, $token),
                'minutes' => config('auth.passwords.users.expire'),
            ]));
    }
}
