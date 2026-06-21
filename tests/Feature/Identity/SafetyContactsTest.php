<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Authorization\Database\Seeders\PermissionCatalogueSeeder;
use App\Domains\Identity\Models\SafetyContact;
use App\Domains\Notification\Notifications\SafetyContactEmergencyNotification;
use App\Domains\Organization\Database\Seeders\MonitoringOrganizationSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class SafetyContactsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogueSeeder::class);
        $this->seed(MonitoringOrganizationSeeder::class);
    }

    public function test_user_can_add_list_and_delete_contacts(): void
    {
        $user = User::factory()->create();

        $id = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/contacts', ['name' => 'Lisa', 'phone' => '+2348010000001'])
            ->assertCreated()
            ->assertJsonPath('data.is_primary', true) // first contact becomes primary
            ->json('data.id');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/contacts')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/me/contacts/{$id}")->assertNoContent();
        $this->assertSame(0, SafetyContact::query()->where('user_id', $user->getKey())->count());
    }

    public function test_max_five_contacts_enforced(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/me/contacts', ['name' => "C{$i}", 'phone' => "+23480100000{$i}"])
                ->assertCreated();
        }

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/contacts', ['name' => 'Sixth', 'phone' => '+2348010000099'])
            ->assertStatus(422);
    }

    public function test_primary_is_exclusive(): void
    {
        $user = User::factory()->create();
        $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/me/contacts', ['name' => 'A', 'phone' => '+2348010000001'])->json('data.id');
        $second = $this->actingAs($user, 'sanctum')->postJson('/api/v1/me/contacts', ['name' => 'B', 'phone' => '+2348010000002', 'is_primary' => true])->json('data.id');

        $this->assertFalse(SafetyContact::findOrFail($first)->is_primary);
        $this->assertTrue(SafetyContact::findOrFail($second)->is_primary);
    }

    public function test_cannot_touch_another_users_contact(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $contact = SafetyContact::create(['user_id' => $alice->getKey(), 'name' => 'A', 'phone' => '+2348010000001', 'is_primary' => true]);

        $this->actingAs($bob, 'sanctum')
            ->patchJson("/api/v1/me/contacts/{$contact->getKey()}", ['name' => 'hijack'])
            ->assertNotFound();
    }

    public function test_triggering_sos_notifies_safety_contacts(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        SafetyContact::create(['user_id' => $user->getKey(), 'name' => 'Lisa', 'phone' => '+2348011111111', 'email' => 'lisa@example.com', 'is_primary' => true]);
        SafetyContact::create(['user_id' => $user->getKey(), 'name' => 'Tunde', 'phone' => '+2348022222222']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/emergencies', ['message' => 'Help', 'lat' => 6.5244, 'lng' => 3.3792])
            ->assertCreated();

        foreach (['+2348011111111', '+2348022222222'] as $phone) {
            Notification::assertSentOnDemand(
                SafetyContactEmergencyNotification::class,
                fn (SafetyContactEmergencyNotification $n, array $channels, AnonymousNotifiable $notifiable): bool =>
                    ($notifiable->routes['sms'] ?? null) === $phone,
            );
        }
    }
}
