<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\DTOs;

use App\Domains\Shared\Data\DataTransferObject;
use Illuminate\Http\Request;

final class RegisterWebhookData extends DataTransferObject
{
    /**
     * @param list<string> $events
     */
    public function __construct(
        public readonly string $url,
        public readonly array $events,
        public readonly ?string $description = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $events */
        $events = array_values(array_unique(array_map(
            static fn (mixed $event): string => (string) $event,
            (array) $request->input('events', []),
        )));

        return new self(
            url: $request->string('url')->trim()->value(),
            events: $events,
            description: $request->input('description'),
        );
    }
}
