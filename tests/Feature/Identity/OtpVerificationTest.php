<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Identity\Notifications\VerificationCodeNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class OtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Registration fires UserRegistered → CreateDefaultOrganization, which
        // provisions org roles from the permission catalogue.
        $this->seed(PermissionCatalogueSeeder::class);
    }

    /** Register on mobile (device_name) → token + email OTP issued. */
    private function registerMobile(): array
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Amara',
            'email' => 'amara@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'device_name' => 'iphone',
        ])->assertCreated();

        // Response is enveloped by WrapApiResponse → { data: { user, token } }.
        $user = User::findOrFail($response->json('data.user.id'));

        $code = null;
        Notification::assertSentTo($user, VerificationCodeNotification::class, function (VerificationCodeNotification $n) use (&$code): bool {
            if ($n->channel === 'email') {
                $code = $n->code;
            }
            return $n->channel === 'email';
        });

        return [$user, (string) $code];
    }

    public function test_register_issues_an_email_otp(): void
    {
        [$user, $code] = $this->registerMobile();

        $this->assertNotEmpty($code);
        $this->assertSame(6, strlen($code));
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('verification_codes', ['user_id' => $user->getKey(), 'channel' => 'email']);
    }

    public function test_consumer_registration_does_not_create_a_personal_org(): void
    {
        // ADR-0001: mobile/token signups are user-scoped and get no workspace.
        [$user] = $this->registerMobile();

        $this->assertFalse($user->fresh()->organizations()->exists());
    }

    public function test_correct_code_verifies_email(): void
    {
        [$user, $code] = $this->registerMobile();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/otp/verify', ['channel' => 'email', 'code' => $code])
            ->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_incorrect_code_is_rejected(): void
    {
        [$user] = $this->registerMobile();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/otp/verify', ['channel' => 'email', 'code' => 'badcode'])
            ->assertStatus(422);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_expired_code_is_rejected(): void
    {
        [$user, $code] = $this->registerMobile();

        // TTL default is 180s; jump past it.
        $this->travel(4)->minutes();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/otp/verify', ['channel' => 'email', 'code' => $code])
            ->assertStatus(422);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_issues_a_new_code(): void
    {
        [$user] = $this->registerMobile();

        Notification::fake();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/otp/resend', ['channel' => 'email'])
            ->assertOk();

        Notification::assertSentTo($user, VerificationCodeNotification::class);
    }
}
