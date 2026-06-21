<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Requests;

use App\Domains\Responder\Support\Enums\ResponderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorization is intentionally permissive here (any authenticated org member);
 * the controller decides between self-service (`responders.self`) and managing
 * another responder (`responders.manage`), because the rule depends on whether
 * the target profile belongs to the caller.
 */
final class ChangeResponderStatusRequest extends FormRequest
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
        return [
            'status' => ['required', Rule::in(ResponderStatus::values())],
        ];
    }
}
