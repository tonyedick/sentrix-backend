<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Models;

use App\Domains\RidesMarket\Support\Enums\DeliveryPaymentMethod;
use App\Domains\RidesMarket\Support\Enums\DeliveryStatus;
use App\Domains\RidesMarket\Support\Enums\ParcelSize;
use App\Domains\Shared\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Sentrix Send parcel delivery. User-scoped (ADR-0001): belongs to the
 * authenticated sender. The courier is the same simulated verified fleet
 * (denormalised snapshot). Pay up-front from wallet or Cash-on-Delivery.
 * ALL MONEY IS INTEGER CENTS.
 */
final class Delivery extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'parcel_size',
        'pickup_label',
        'pickup_lat',
        'pickup_lng',
        'dropoff_label',
        'dropoff_lat',
        'dropoff_lng',
        'distance_km',
        'fare_cents',
        'cod_amount_cents',
        'payment_method',
        'status',
        'recipient_name',
        'recipient_phone',
        'driver_name',
        'match_code',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parcel_size' => ParcelSize::class,
            'pickup_lat' => 'decimal:7',
            'pickup_lng' => 'decimal:7',
            'dropoff_lat' => 'decimal:7',
            'dropoff_lng' => 'decimal:7',
            'distance_km' => 'decimal:2',
            'fare_cents' => 'integer',
            'cod_amount_cents' => 'integer',
            'payment_method' => DeliveryPaymentMethod::class,
            'status' => DeliveryStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
