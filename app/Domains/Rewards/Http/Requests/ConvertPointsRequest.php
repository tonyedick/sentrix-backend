<?php

declare(strict_types=1);

namespace App\Domains\Rewards\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Convert points into a Premium pack. The pack must be one of the configured
 * premium packs; sufficiency is enforced in the service (422 on shortfall).
 */
final class ConvertPointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var array<string, mixed> $packs */
        $packs = config('sentrix.rewards.premium_packs', []);

        return [
            'pack_id' => ['required', 'string', Rule::in(array_keys($packs))],
        ];
    }
}
