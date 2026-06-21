<?php

declare(strict_types=1);

namespace App\Domains\Identity\Providers;

use App\Domains\Identity\Contracts\SocialVerifier;
use App\Domains\Identity\Events\UserRegistered;
use App\Domains\Identity\Listeners\SendEmailVerificationLink;
use App\Domains\Identity\Support\StubSocialVerifier;
use App\Domains\Shared\Providers\DomainServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class IdentityServiceProvider extends DomainServiceProvider
{
    public function register(): void
    {
        // Social token verification driver (swap stub → apple/google in prod).
        $this->app->bind(SocialVerifier::class, static function (): SocialVerifier {
            return match ((string) config('sentrix.auth.social.driver', 'stub')) {
                default => new StubSocialVerifier(),
            };
        });
    }

    public function boot(): void
    {
        $this->configurePasswordPolicy();
        $this->configureRateLimiting();
        $this->configureNotificationUrls();
        $this->registerListeners();
        $this->loadDomainMigrations();
        $this->loadDomainApiRoutes();
    }

    protected function domainPath(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Global password strength policy applied wherever Password::defaults() is
     * used (registration, reset). Strong in production — including a check
     * against known-breached passwords — and relaxed elsewhere so local/test
     * fixtures stay simple.
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(static function (): Password {
            if (! app()->isProduction()) {
                return Password::min(8);
            }

            return Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised();
        });
    }

    /**
     * Login/credential throttling, layered to resist brute force without
     * enabling targeted account-lockout denial of service:
     *
     *   - 5/min per (email + IP): blunts guessing a single account, but a victim
     *     is never locked out by an attacker coming from a different IP; and
     *   - 20/min per IP: caps an attacker cycling through many accounts.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', static function (Request $request): array {
            $email = Str::lower((string) $request->input('email'));
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(5)->by('auth:'.sha1($email.'|'.$ip)),
                Limit::perMinute(20)->by('auth-ip:'.$ip),
            ];
        });
    }

    /**
     * Point the email-verification and password-reset links at the right place.
     *
     * Verification links hit the backend signed route (which verifies, then
     * redirects to the front-end). Password-reset links go straight to the
     * front-end, which collects the new password and POSTs it back to the API.
     */
    private function configureNotificationUrls(): void
    {
        VerifyEmail::createUrlUsing(static function (object $notifiable): string {
            return URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1((string) $notifiable->getEmailForVerification()),
                ],
            );
        });

        ResetPassword::createUrlUsing(static function (CanResetPassword $notifiable, string $token): string {
            $base = rtrim((string) config('app.frontend_url'), '/');
            $email = urlencode($notifiable->getEmailForPasswordReset());

            return "{$base}/password/reset/{$token}?email={$email}";
        });
    }

    private function registerListeners(): void
    {
        // Send a verification link as soon as a user registers (queued).
        Event::listen(UserRegistered::class, SendEmailVerificationLink::class);
    }
}
