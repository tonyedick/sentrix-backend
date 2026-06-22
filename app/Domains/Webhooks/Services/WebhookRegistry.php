<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Services;

use App\Domains\Organization\Models\Organization;
use App\Domains\Webhooks\DTOs\RegisterWebhookData;
use App\Domains\Webhooks\Models\Webhook;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns the webhook registry write path: registering an endpoint (with a freshly
 * generated signing secret) and removing one.
 */
final readonly class WebhookRegistry
{
    public function register(Organization $organization, User $actor, RegisterWebhookData $data): Webhook
    {
        return DB::transaction(function () use ($organization, $actor, $data): Webhook {
            $webhook = new Webhook([
                'organization_id' => $organization->getKey(),
                'created_by' => $actor->getKey(),
                'url' => $data->url,
                'events' => $data->events,
                'secret' => Str::random(40),
                'active' => true,
                'description' => $data->description,
            ]);
            $webhook->save();

            return $webhook;
        });
    }

    public function remove(Webhook $webhook): void
    {
        DB::transaction(static function () use ($webhook): void {
            $webhook->delete();
        });
    }
}
