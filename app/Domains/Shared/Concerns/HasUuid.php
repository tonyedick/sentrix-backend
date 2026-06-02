<?php

declare(strict_types=1);

namespace App\Domains\Shared\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Gives an Eloquent model an ordered (COMB) UUID primary key.
 *
 * Ordered UUIDs keep B-tree index locality high under heavy insert load,
 * which matters for the queue-first, high-write tables in Sentrix.
 *
 * @mixin Model
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(static function (Model $model): void {
            $key = $model->getKeyName();

            if (empty($model->{$key})) {
                $model->{$key} = (string) Str::orderedUuid();
            }
        });
    }

    public function initializeHasUuid(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }
}
