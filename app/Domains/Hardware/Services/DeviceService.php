<?php

declare(strict_types=1);

namespace App\Domains\Hardware\Services;

use App\Domains\Hardware\DTOs\RegisterDeviceData;
use App\Domains\Hardware\Events\DeviceRegistered;
use App\Domains\Hardware\Events\DeviceResynced;
use App\Domains\Hardware\Models\Device;
use App\Domains\Hardware\Support\Enums\DeviceStatus;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owns the hardware-device registry lifecycle: registration, check-in (resync),
 * and read-only diagnostics. Writes run in a transaction and emit a broadcast +
 * audited event; resync locks the row so concurrent check-ins are serialized.
 */
final readonly class DeviceService
{
    /**
     * Number of minutes since last_seen_at after which an active device is
     * considered "stale" in a diagnostic snapshot.
     */
    private const STALE_AFTER_MINUTES = 15;

    public function register(Organization $organization, User $actor, RegisterDeviceData $data): Device
    {
        return DB::transaction(function () use ($organization, $actor, $data): Device {
            $device = Device::create([
                'organization_id' => $organization->getKey(),
                'registered_by' => $actor->getKey(),
                'kind' => $data->kind,
                'serial' => $data->serial,
                'name' => $data->name,
                'site' => $data->site,
                'zone' => $data->zone,
                'status' => DeviceStatus::Active,
                'last_seen_at' => now(),
                'metadata' => $data->metadata,
            ]);

            event(new DeviceRegistered($device, $actor->getKey(), [
                'kind' => $device->kind->value,
                'serial' => $device->serial,
                'status' => $device->status->value,
            ]));

            return $device;
        });
    }

    /**
     * Device checked back in: mark it active and stamp last_seen_at. Concurrency
     * safe — the row is locked and re-read before mutation. Idempotent enough to
     * retry (it always converges on active + a fresh timestamp).
     */
    public function resync(Device $device, User $actor): Device
    {
        return DB::transaction(function () use ($device, $actor): Device {
            /** @var Device $locked */
            $locked = Device::query()->whereKey($device->getKey())->lockForUpdate()->firstOrFail();

            $locked->update([
                'status' => DeviceStatus::Active,
                'last_seen_at' => now(),
            ]);

            event(new DeviceResynced($locked, $actor->getKey(), [
                'status' => $locked->status->value,
                'last_seen_at' => $locked->last_seen_at?->toIso8601String(),
            ]));

            return $locked;
        });
    }

    /**
     * Read-only diagnostic snapshot: the current status, last_seen_at, and a
     * derived health string (online | stale | offline). No state change.
     *
     * @return array{status: string, last_seen_at: string|null, health: string}
     */
    public function diagnose(Device $device): array
    {
        return [
            'status' => $device->status->value,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'health' => $this->health($device),
        ];
    }

    private function health(Device $device): string
    {
        if ($device->status !== DeviceStatus::Active) {
            return 'offline';
        }

        $lastSeen = $device->last_seen_at;

        if (! $lastSeen instanceof Carbon) {
            return 'stale';
        }

        return $lastSeen->gt(now()->subMinutes(self::STALE_AFTER_MINUTES))
            ? 'online'
            : 'stale';
    }
}
