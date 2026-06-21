<?php

declare(strict_types=1);

namespace App\Domains\Insurance\Http\Controllers;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Insurance\Services\RiskService;
use App\Domains\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The synergy layer: insurers price on risk, and Sentrix measures it. Both
 * endpoints are read-only / computed — they derive a snapshot from live platform
 * signals with no state change and no event, so they skip the Resource and return
 * a plain `data` array.
 */
final class RiskController extends Controller
{
    public function __construct(private readonly RiskService $risk) {}

    /**
     * The organization's current risk profile: a 0-100 score, a band, and the
     * contributing factors.
     */
    public function profile(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->can(DefaultPermission::InsuranceRisk->value), Response::HTTP_FORBIDDEN);

        return response()->json(['data' => $this->risk->profile($organization)]);
    }

    /**
     * A risk-priced annual premium with the Sentrix saving versus a flat
     * baseline insurer.
     */
    public function quote(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->can(DefaultPermission::InsuranceQuote->value), Response::HTTP_FORBIDDEN);

        $currency = $request->filled('currency')
            ? $request->string('currency')->upper()->value()
            : 'USD';

        return response()->json(['data' => $this->risk->quote($organization, $currency)]);
    }
}
