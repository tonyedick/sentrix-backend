<?php

declare(strict_types=1);

namespace Tests\Feature\VisionGuard;

use App\Domains\VisionGuard\Models\CameraSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VisionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_and_list_sources(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/sources', ['type' => 'dashcam', 'label' => 'Toyota Camry 2023'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'dashcam');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/sources')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_upload_url_then_finalize_media(): void
    {
        $user = User::factory()->create();

        $target = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/media/upload-url', ['content_type' => 'video/mp4'])
            ->assertCreated()
            ->json('data');

        $this->assertArrayHasKey('key', $target);
        $this->assertArrayHasKey('upload_url', $target);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/media', ['storage_key' => $target['key'], 'content_type' => 'video/mp4'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'uploaded');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/media')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_cannot_modify_another_users_source(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $source = CameraSource::create([
            'user_id' => $alice->getKey(), 'type' => 'phone', 'label' => 'Alice phone', 'status' => 'active',
        ]);

        $this->actingAs($bob, 'sanctum')
            ->patchJson("/api/v1/me/sources/{$source->getKey()}", ['label' => 'hijacked'])
            ->assertNotFound();
    }
}
