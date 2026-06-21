<?php

declare(strict_types=1);

namespace App\Domains\RidesMarket\Services;

use App\Domains\Rides\Models\Ride;
use App\Domains\Rides\Models\RideSafety;
use App\Domains\Rides\Support\Enums\PaymentMethod as RidePaymentMethod;
use App\Domains\Rides\Support\Enums\RideClass;
use App\Domains\Rides\Support\Enums\RideStatus;
use App\Domains\RidesMarket\DTOs\BookDeliveryData;
use App\Domains\RidesMarket\DTOs\CreateOfferData;
use App\Domains\RidesMarket\DTOs\PlaceBidData;
use App\Domains\RidesMarket\DTOs\SendQuoteData;
use App\Domains\RidesMarket\Events\DeliveryBooked;
use App\Domains\RidesMarket\Events\OfferCreated;
use App\Domains\RidesMarket\Events\OfferMatched;
use App\Domains\RidesMarket\Models\Delivery;
use App\Domains\RidesMarket\Models\RideBid;
use App\Domains\RidesMarket\Models\RideOffer;
use App\Domains\RidesMarket\Support\Enums\BidKind;
use App\Domains\RidesMarket\Support\Enums\BidStatus;
use App\Domains\RidesMarket\Support\Enums\DeliveryPaymentMethod;
use App\Domains\RidesMarket\Support\Enums\DeliveryStatus;
use App\Domains\RidesMarket\Support\Enums\OfferStatus;
use App\Domains\RidesMarket\Support\Enums\ParcelSize;
use App\Domains\RidesMarket\Support\Enums\PricingFlag;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Safe Rides — Marketplace (name-your-price) + Sentrix Send (parcel delivery).
 * User-scoped (ADR-0001). Mirrors the SentrixGo rides_market / rides_send routers,
 * adapted to Laravel + integer cents. The driver/courier pool is SIMULATED and
 * deterministic — the real KYC-verified pool integrates with the Driver domain.
 *
 * Fare engine: this replicates RideService's go_safe fare formula. RideService's
 * constants are PRIVATE, so we re-declare the same values here and note it; if
 * they ever change there, change them here too. The fair estimate is the go_safe
 * fare for the leg at surge 1.0 (negotiation is on price, not surge).
 *
 * ALL MONEY IS INTEGER CENTS.
 */
final readonly class RidesMarketService
{
    // Replicated from Rides\Services\RideService (its constants are private).
    // go_safe base fare components in NGN cents (kobo).
    private const BASE_FARE_CENTS = 60000;     // flat pickup fee
    private const PER_KM_CENTS = 20000;        // per km
    private const BOOKING_FEE_CENTS = 10000;   // platform fee
    private const MIN_FARE_CENTS = 80000;      // floor

    // Pricing-flag thresholds (fraction of the fair estimate).
    private const FLAG_LOW = 0.8;              // < 0.8x fair  => low
    private const FLAG_HIGH = 1.2;             // > 1.2x fair  => high
    private const FLOOR = 0.6;                 // < 0.6x fair  => offer_too_low (422)

    // Simulated deterministic verified-driver pool (matches RideService's pool).
    private const DRIVER_POOL = [
        ['id' => 'sd-4f2a9c', 'name' => 'Emeka U.'],
        ['id' => 'sd-9b7e11', 'name' => 'Bola A.'],
        ['id' => 'sd-1c3d55', 'name' => 'Sani M.'],
    ];

    public function __construct(private DatabaseManager $db) {}

    // ---- Marketplace: create offer + seed simulated bids -------------------

    public function createOffer(User $rider, CreateOfferData $data): RideOffer
    {
        return $this->db->transaction(function () use ($rider, $data): RideOffer {
            $distanceKm = $this->distanceKm($data->originLat, $data->originLng, $data->destLat, $data->destLng);
            $fairEstimate = $this->fareCents(RideClass::GoSafe, $distanceKm);

            // Below-60%-of-fair gate: reject as an unviable fare.
            if ($data->proposedFareCents < (int) round($fairEstimate * self::FLOOR)) {
                throw ValidationException::withMessages([
                    'proposed_fare_cents' => ['offer_too_low'],
                ]);
            }

            $flag = $this->pricingFlag($data->proposedFareCents, $fairEstimate);

            $offer = RideOffer::create([
                'user_id' => $rider->getKey(),
                'origin_label' => $data->originLabel,
                'origin_lat' => $data->originLat,
                'origin_lng' => $data->originLng,
                'dest_label' => $data->destLabel,
                'dest_lat' => $data->destLat,
                'dest_lng' => $data->destLng,
                'distance_km' => round($distanceKm, 2),
                'proposed_fare_cents' => $data->proposedFareCents,
                'fair_estimate_cents' => $fairEstimate,
                'pricing_flag' => $flag->value,
                'status' => OfferStatus::Open->value,
            ]);

            $this->seedBids($offer, $data->proposedFareCents, $fairEstimate);

            event(new OfferCreated($offer));

            return $offer->refresh()->load('bids');
        });
    }

    /**
     * Seed 2-3 deterministic simulated driver bids (mirrors rides_market
     * _seed_market_bids): a couple near the fair estimate, one near the proposed
     * price. Drivers near the rider's price ACCEPT; the rest COUNTER toward the
     * midpoint of proposed and fair.
     */
    private function seedBids(RideOffer $offer, int $proposedCents, int $fairCents): void
    {
        foreach (self::DRIVER_POOL as $index => $driver) {
            if ($index === 0) {
                // Anchor bid near the rider's proposed price.
                $amount = $proposedCents;
                $kind = $proposedCents >= (int) round($fairCents * 0.92) ? BidKind::Accept : BidKind::Counter;
            } else {
                // Counter toward the midpoint of proposed and fair, stepped per driver.
                $midpoint = (int) round(min($fairCents, ($proposedCents + $fairCents) / 2));
                $amount = $midpoint + ($index * 5000); // +NGN50 step per driver
                $kind = BidKind::Counter;
            }

            RideBid::create([
                'ride_offer_id' => $offer->getKey(),
                'driver_id' => $driver['id'],
                'driver_name' => $driver['name'],
                'amount_cents' => max(self::MIN_FARE_CENTS, $amount),
                'kind' => $kind->value,
                'status' => BidStatus::Pending->value,
            ]);
        }
    }

    // ---- Marketplace: a driver bids on an open offer -----------------------

    /**
     * Create a pending bid on an OPEN offer. SIMPLIFICATION: the caller is the
     * authenticated user but is assumed to be acting driver-side — real driver
     * gating (DriverOnboarding active status) lands later. The driver snapshot
     * is taken from the supplied name (or a deterministic pool fallback).
     */
    public function placeBid(RideOffer $offer, PlaceBidData $data): RideBid
    {
        return $this->db->transaction(function () use ($offer, $data): RideBid {
            /** @var RideOffer $locked */
            $locked = RideOffer::query()->whereKey($offer->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OfferStatus::Open) {
                throw ValidationException::withMessages([
                    'status' => ['This offer is no longer open for bids.'],
                ]);
            }

            $fallback = self::DRIVER_POOL[abs(crc32((string) $locked->getKey())) % count(self::DRIVER_POOL)];

            return RideBid::create([
                'ride_offer_id' => $locked->getKey(),
                'driver_id' => $fallback['id'],
                'driver_name' => $data->driverName ?? $fallback['name'],
                'amount_cents' => $data->amountCents,
                'kind' => $data->kind->value,
                'status' => BidStatus::Pending->value,
            ]);
        });
    }

    // ---- Marketplace: rider accepts a bid -> materialise a Ride ------------

    /**
     * The rider accepts a bid. Locks the offer, marks the chosen bid accepted and
     * the rest rejected, MATERIALISES a real Ride (status=matched) at the agreed
     * fare via the Rides domain Ride model + its RideSafety row (matching
     * RideService::request behaviour), then closes the offer.
     *
     * @return array{offer: RideOffer, ride_id: string}
     */
    public function acceptBid(RideOffer $offer, string $bidId): array
    {
        return $this->db->transaction(function () use ($offer, $bidId): array {
            /** @var RideOffer $locked */
            $locked = RideOffer::query()->whereKey($offer->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OfferStatus::Open) {
                throw ValidationException::withMessages([
                    'status' => ['This offer is already closed.'],
                ]);
            }

            // Look the bid up WITHIN the offer (never a raw where on unvalidated
            // input); guard the uuid shape before whereKey on Postgres.
            if (! Str::isUuid($bidId)) {
                throw ValidationException::withMessages([
                    'bid_id' => ['Unknown bid for this offer.'],
                ]);
            }

            /** @var RideBid|null $bid */
            $bid = RideBid::query()
                ->where('ride_offer_id', $locked->getKey())
                ->whereKey($bidId)
                ->lockForUpdate()
                ->first();

            if ($bid === null) {
                throw ValidationException::withMessages([
                    'bid_id' => ['Unknown bid for this offer.'],
                ]);
            }

            // Mark the chosen bid accepted, the rest rejected.
            $bid->forceFill(['status' => BidStatus::Accepted->value])->save();
            RideBid::query()
                ->where('ride_offer_id', $locked->getKey())
                ->whereKeyNot($bid->getKey())
                ->update(['status' => BidStatus::Rejected->value]);

            $ride = $this->materialiseRide($locked, $bid);

            $locked->forceFill([
                'status' => OfferStatus::Matched->value,
                'matched_ride_id' => $ride->getKey(),
            ])->save();

            event(new OfferMatched($locked->refresh(), $ride->getKey()));

            return ['offer' => $locked->load('bids'), 'ride_id' => $ride->getKey()];
        });
    }

    /**
     * Insert a real Ride at the negotiated fare so it flows into live tracking +
     * the ops board, plus its 1:1 RideSafety row (mirrors RideService::request).
     * option/class is go_safe; the agreed bid amount is both the estimate and the
     * final fare snapshot. The driver is the bid's simulated snapshot.
     */
    private function materialiseRide(RideOffer $offer, RideBid $bid): Ride
    {
        $originLat = (float) $offer->origin_lat;
        $originLng = (float) $offer->origin_lng;

        $ride = Ride::create([
            'user_id' => $offer->user_id,
            'ride_class' => RideClass::GoSafe->value,
            'status' => RideStatus::Matched->value,
            'origin_label' => $offer->origin_label,
            'origin_lat' => $offer->origin_lat,
            'origin_lng' => $offer->origin_lng,
            'dest_label' => $offer->dest_label,
            'dest_lat' => $offer->dest_lat,
            'dest_lng' => $offer->dest_lng,
            'distance_km' => $offer->distance_km,
            'fare_estimate_cents' => $bid->amount_cents,
            'tip_cents' => 0,
            'currency' => 'NGN',
            'surge_multiplier' => 1.0,
            'payment_method' => RidePaymentMethod::Cash->value,
            'match_code' => $this->matchCode(),
            'driver_id' => $bid->driver_id,
            'driver_name' => $bid->driver_name,
            'driver_lat' => round($originLat + 0.004, 7),
            'driver_lng' => round($originLng + 0.004, 7),
            'driver_eta_minutes' => 4,
            'driver_speed_kph' => 0,
            'requested_at' => now(),
        ]);

        // 1:1 safety row, exactly as RideService::request creates it.
        RideSafety::create(['ride_id' => $ride->getKey()]);

        return $ride;
    }

    // ---- Sentrix Send: quote (no persistence) ------------------------------

    /**
     * Quote a parcel delivery: base go_safe fare for the leg x parcel multiplier.
     *
     * @return array{
     *     distance_km: float,
     *     currency: string,
     *     parcel_size: string,
     *     base_fare_cents: int,
     *     multiplier: float,
     *     fare_cents: int
     * }
     */
    public function quoteDelivery(SendQuoteData $data): array
    {
        $distanceKm = $this->distanceKm($data->pickupLat, $data->pickupLng, $data->dropoffLat, $data->dropoffLng);
        $baseFare = $this->fareCents(RideClass::GoSafe, $distanceKm);
        $fareCents = $this->parcelFareCents($baseFare, $data->parcelSize);

        return [
            'distance_km' => round($distanceKm, 2),
            'currency' => 'NGN',
            'parcel_size' => $data->parcelSize->value,
            'base_fare_cents' => $baseFare,
            'multiplier' => $data->parcelSize->fareMultiplier(),
            'fare_cents' => $fareCents,
        ];
    }

    // ---- Sentrix Send: book ------------------------------------------------

    public function bookDelivery(User $sender, BookDeliveryData $data): Delivery
    {
        return $this->db->transaction(function () use ($sender, $data): Delivery {
            $distanceKm = $this->distanceKm($data->pickupLat, $data->pickupLng, $data->dropoffLat, $data->dropoffLng);
            $baseFare = $this->fareCents(RideClass::GoSafe, $distanceKm);
            $fareCents = $this->parcelFareCents($baseFare, $data->parcelSize);

            // COD only applies when paying Cash-on-Delivery; wallet pre-pay carries no COD.
            $codCents = $data->paymentMethod === DeliveryPaymentMethod::Cod ? $data->codAmountCents : 0;

            $courier = $this->matchCourier($sender);

            $delivery = Delivery::create([
                'user_id' => $sender->getKey(),
                'parcel_size' => $data->parcelSize->value,
                'pickup_label' => $data->pickupLabel ?? 'Pickup',
                'pickup_lat' => $data->pickupLat,
                'pickup_lng' => $data->pickupLng,
                'dropoff_label' => $data->dropoffLabel ?? 'Drop-off',
                'dropoff_lat' => $data->dropoffLat,
                'dropoff_lng' => $data->dropoffLng,
                'distance_km' => round($distanceKm, 2),
                'fare_cents' => $fareCents,
                'cod_amount_cents' => $codCents,
                'payment_method' => $data->paymentMethod->value,
                'status' => DeliveryStatus::Matched->value,
                'recipient_name' => $data->recipientName,
                'recipient_phone' => $data->recipientPhone,
                'driver_name' => $courier['name'],
                'match_code' => $this->matchCode(),
            ]);

            event(new DeliveryBooked($delivery));

            return $delivery;
        });
    }

    // ---- Fare engine + helpers (replicated from RideService) ----------------

    /**
     * Per-class fare in cents at surge 1.0: (base + per_km*km + booking)*class,
     * floored. The fair estimate / Send base fare use go_safe.
     */
    private function fareCents(RideClass $class, float $distanceKm): int
    {
        $mult = $class->fareMultiplier();
        $subtotal = (self::BASE_FARE_CENTS * $mult)
            + (self::PER_KM_CENTS * max(0.0, $distanceKm) * $mult)
            + self::BOOKING_FEE_CENTS;

        return max((int) round($subtotal), self::MIN_FARE_CENTS);
    }

    private function parcelFareCents(int $baseFareCents, ParcelSize $size): int
    {
        return (int) round($baseFareCents * $size->fareMultiplier());
    }

    private function pricingFlag(int $proposedCents, int $fairCents): PricingFlag
    {
        $ratio = $fairCents > 0 ? $proposedCents / $fairCents : 1.0;

        if ($ratio < self::FLAG_LOW) {
            return PricingFlag::Low;
        }

        if ($ratio > self::FLAG_HIGH) {
            return PricingFlag::High;
        }

        return PricingFlag::Fair;
    }

    /**
     * Great-circle distance in km, padded ~1.3x for road distance and floored at
     * 1km (mirrors RideService::distanceKm and the SentrixGo quote engine).
     */
    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = (sin($dLat / 2) ** 2)
            + (cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLng / 2) ** 2));
        $straight = $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));

        return max(1.0, $straight * 1.3);
    }

    /**
     * @return array{id: string, name: string}
     */
    private function matchCourier(User $sender): array
    {
        $index = abs(crc32((string) $sender->getKey())) % count(self::DRIVER_POOL);

        return self::DRIVER_POOL[$index];
    }

    private function matchCode(): string
    {
        return Str::padLeft((string) random_int(0, 9999), 4, '0');
    }
}
