<?php

declare(strict_types=1);

namespace App\Domains\Shared\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Lightweight base class for immutable DTOs.
 *
 * Concrete DTOs declare readonly promoted properties and a static
 * factory (e.g. fromRequest). This base only provides array casting
 * so DTOs can be handed straight to Eloquent / fill().
 *
 * @implements Arrayable<string, mixed>
 */
abstract class DataTransferObject implements Arrayable
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Return the DTO as an array excluding null values.
     *
     * @return array<string, mixed>
     */
    public function toFilledArray(): array
    {
        return array_filter(
            $this->toArray(),
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
