<?php

declare(strict_types=1);

namespace App\Domains\Evidence\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bookmark / un-bookmark an observation. Reuses the evidence.hold ability
 * (curation is a custodial action). The `bookmarked` flag is optional — omit it
 * to flip the current state.
 */
final class BookmarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::EvidenceHold->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bookmarked' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
