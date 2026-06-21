<?php

declare(strict_types=1);

namespace App\Domains\Command\Services;

use App\Domains\Command\DTOs\RouteIncidentData;
use App\Domains\Command\Events\CommandIncidentRouted;
use App\Domains\Command\Models\Agency;
use App\Domains\Command\Models\Command;
use App\Domains\Command\Models\CommandIncident;
use App\Domains\Command\Support\Enums\AgencyStatus;
use App\Domains\Command\Support\Enums\CommandIncidentStatus;
use App\Domains\Command\Support\Enums\CommandTier;
use App\Domains\Command\Support\Enums\IncidentCategory;
use App\Domains\Command\Support\Enums\IncidentSeverity;
use Illuminate\Support\Facades\DB;

/**
 * The routing doctrine, mirrored from the Omni reference (lib/seccmd.js +
 * lib/cad.js):
 *
 *   categorize -> leadAgencyFor(country, category) -> nearest command of that
 *   agency (deepest tier within ~60km of lat/lng via haversine; else the
 *   agency's national HQ) -> open a CommandIncident with SLA due times.
 *
 * Stateless final readonly service; the write runs in a transaction.
 */
final readonly class CommandRoutingService
{
    /** Routing radius for the nearest-command search (km). Matches seccmd.js. */
    private const NEAREST_RADIUS_KM = 60.0;

    private const EARTH_KM = 6371.0;

    private const DEFAULT_COUNTRY = 'NG';

    /**
     * SLA targets in SECONDS, grounded in NFPA 1710 (alarm-handling + travel) —
     * a mirror of cad.js SLA_BY_SEVERITY: time-to-dispatch and time-to-on-scene.
     *
     * @var array<string, array{dispatch: int, onscene: int}>
     */
    private const SLA_BY_SEVERITY = [
        'critical' => ['dispatch' => 90, 'onscene' => 480],   // 90s / 8m
        'high' => ['dispatch' => 120, 'onscene' => 600],      // 2m / 10m
        'medium' => ['dispatch' => 300, 'onscene' => 1200],   // 5m / 20m
        'low' => ['dispatch' => 600, 'onscene' => 3600],      // 10m / 1h
    ];

    /** Life-safety categories run tighter (85% of base) — mirrors cad.js. */
    private const TIGHT_CATEGORIES = ['fire', 'medical'];

    private const TIGHT_FACTOR = 0.85;

    /**
     * Category derivation rules — mirrors seccmd.js CATEGORY_RULES, ordered.
     * First matching pattern (case-insensitive) on the summary wins; unmapped
     * emergencies default to crime (the police catch-all).
     *
     * @var list<array{re: string, cat: IncidentCategory}>
     */
    private const CATEGORY_RULES = [
        ['re' => '/fire|smoke|explos|gas[_ ]?leak|burn/i', 'cat' => IncidentCategory::Fire],
        ['re' => '/crash|collision|harsh[_ ]?brak|overspeed|traffic|road|vehicle/i', 'cat' => IncidentCategory::Traffic],
        ['re' => '/medical|injur|cardiac|unconscious|ambulance|health/i', 'cat' => IncidentCategory::Medical],
        ['re' => '/flood|collapse|disaster|storm|earthquake|landslide/i', 'cat' => IncidentCategory::Disaster],
        ['re' => '/vandal|pipeline|infrastructur|facility[_ ]?breach/i', 'cat' => IncidentCategory::Civil],
        ['re' => '/weapon|gun|firearm|knife|armed|robbery|theft|burgl|intru|aggress|assault|kidnap|sos|panic|loiter|perimeter/i', 'cat' => IncidentCategory::Crime],
    ];

    /**
     * Route an incident end-to-end and open its envelope. Returns null only when
     * no agency leads the category in the country (and there is no crime
     * fallback agency) — i.e. the country has no responder structure yet.
     */
    public function route(RouteIncidentData $data): ?CommandIncident
    {
        $category = $this->resolveCategory($data);
        $agency = $this->leadAgencyFor($data->country, $category);

        if (! $agency instanceof Agency) {
            return null;
        }

        $command = $this->chooseCommand($agency, $data->lat, $data->lng);

        if (! $command instanceof Command) {
            return null; // agency exists but has no command structure yet
        }

        $sla = $this->slaTarget($category, $data->severity);

        return DB::transaction(function () use ($data, $category, $agency, $command, $sla): CommandIncident {
            $now = now();

            $incident = CommandIncident::create([
                'command_id' => $command->getKey(),
                'agency_id' => $agency->getKey(),
                'category' => $category,
                'severity' => $data->severity,
                'status' => CommandIncidentStatus::New,
                'source_type' => $data->sourceType,
                'source_ref' => $data->sourceRef,
                'summary' => $data->summary,
                'lat' => $data->lat,
                'lng' => $data->lng,
                'sla_dispatch_due_at' => $now->copy()->addSeconds($sla['dispatch']),
                'sla_onscene_due_at' => $now->copy()->addSeconds($sla['onscene']),
                'opened_at' => $now,
            ]);

            event(new CommandIncidentRouted($incident));

            return $incident;
        });
    }

    /**
     * Categorize: honour an explicit category, else derive from the summary
     * (mirrors seccmd.js categorize).
     */
    public function resolveCategory(RouteIncidentData $data): IncidentCategory
    {
        if ($data->category !== null && in_array($data->category, IncidentCategory::values(), true)) {
            return IncidentCategory::from($data->category);
        }

        foreach (self::CATEGORY_RULES as $rule) {
            if (preg_match($rule['re'], $data->summary) === 1) {
                return $rule['cat'];
            }
        }

        return IncidentCategory::Crime; // unmapped emergencies default to police
    }

    /**
     * The agency that LEADS a category in a country: one explicitly declaring it,
     * else the country's catch-all (the crime/police agency), else any active
     * agency. Mirrors seccmd.js leadAgencyFor.
     */
    public function leadAgencyFor(string $country, IncidentCategory $category): ?Agency
    {
        $country = strtoupper($country) ?: self::DEFAULT_COUNTRY;

        /** @var list<Agency> $agencies */
        $agencies = Agency::query()
            ->where('status', AgencyStatus::Active->value)
            ->where('country', $country)
            ->orderBy('created_at')
            ->get()
            ->all();

        $declaring = $this->firstWithCategory($agencies, $category->value);
        if ($declaring instanceof Agency) {
            return $declaring;
        }

        $crime = $this->firstWithCategory($agencies, IncidentCategory::Crime->value);
        if ($crime instanceof Agency) {
            return $crime;
        }

        return $agencies[0] ?? null;
    }

    /**
     * Choose the most specific command of an agency for a point: the nearest
     * command (deepest tier within radius) when coordinates are given, else the
     * agency's national HQ. Mirrors seccmd.js resolveCommand's GPS path with HQ
     * fallback (area/state text matching is out of scope for this slice).
     */
    public function chooseCommand(Agency $agency, ?float $lat, ?float $lng): ?Command
    {
        if ($lat !== null && $lng !== null) {
            $nearest = $this->nearestCommand($agency, $lat, $lng);
            if ($nearest instanceof Command) {
                return $nearest;
            }
        }

        return $this->nationalHq($agency);
    }

    /**
     * Nearest command to a point within the radius, preferring the MOST SPECIFIC
     * tier (a division beats its state HQ when both are near), then distance.
     * Mirrors seccmd.js nearestCommand.
     */
    public function nearestCommand(Agency $agency, float $lat, float $lng, float $radiusKm = self::NEAREST_RADIUS_KM): ?Command
    {
        $candidates = [];

        /** @var Command $command */
        foreach ($agency->commands()->whereNotNull('lat')->whereNotNull('lng')->get() as $command) {
            $distance = $this->haversineKm($lat, $lng, (float) $command->lat, (float) $command->lng);

            if ($distance <= $radiusKm) {
                $candidates[] = ['command' => $command, 'distance' => $distance];
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (array $a, array $b): int {
            /** @var Command $ca */
            $ca = $a['command'];
            /** @var Command $cb */
            $cb = $b['command'];

            $byDepth = $cb->tier->depth() <=> $ca->tier->depth();
            if ($byDepth !== 0) {
                return $byDepth;
            }

            return $a['distance'] <=> $b['distance'];
        });

        /** @var Command $best */
        $best = $candidates[0]['command'];

        return $best;
    }

    private function nationalHq(Agency $agency): ?Command
    {
        /** @var Command|null $hq */
        $hq = $agency->commands()->where('tier', CommandTier::National->value)->first();

        return $hq;
    }

    /**
     * SLA dispatch + on-scene targets (seconds) for a category/severity pair.
     * Mirrors cad.js slaTarget; fire/medical run at 85% of the base.
     *
     * @return array{dispatch: int, onscene: int}
     */
    public function slaTarget(IncidentCategory $category, IncidentSeverity $severity): array
    {
        $base = self::SLA_BY_SEVERITY[$severity->value];

        $factor = in_array($category->value, self::TIGHT_CATEGORIES, true)
            ? self::TIGHT_FACTOR
            : 1.0;

        return [
            'dispatch' => (int) round($base['dispatch'] * $factor),
            'onscene' => (int) round($base['onscene'] * $factor),
        ];
    }

    /**
     * @param  list<Agency>  $agencies
     */
    private function firstWithCategory(array $agencies, string $category): ?Agency
    {
        foreach ($agencies as $agency) {
            $categories = is_array($agency->categories) ? $agency->categories : [];
            if (in_array($category, $categories, true)) {
                return $agency;
            }
        }

        return null;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $toRad = static fn (float $deg): float => $deg * M_PI / 180.0;

        $dLat = $toRad($lat2 - $lat1);
        $dLng = $toRad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_KM * asin(min(1.0, sqrt($a)));
    }
}
