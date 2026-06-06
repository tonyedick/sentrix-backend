<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Registration provisions a default organization, which needs the catalogue.
        $this->seed(PermissionCatalogueSeeder::class);
    }

    public function test_registration_sends_a_verification_link(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-1',
        ])->assertCreated();

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_a_signed_link_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->getKey(),
            'hash' => sha1((string) $user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }
}
