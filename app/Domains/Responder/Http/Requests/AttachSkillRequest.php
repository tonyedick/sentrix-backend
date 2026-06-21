<?php

declare(strict_types=1);

namespace App\Domains\Responder\Http\Requests;

use App\Domains\Authorization\Support\Enums\DefaultPermission;
use App\Domains\Responder\Support\Enums\SkillProficiency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AttachSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(DefaultPermission::RespondersManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'skill_id' => ['required', 'uuid'],
            'proficiency' => ['sometimes', Rule::in(SkillProficiency::values())],
        ];
    }
}
