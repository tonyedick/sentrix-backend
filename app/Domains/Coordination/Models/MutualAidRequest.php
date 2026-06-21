<?php

declare(strict_types=1);

namespace App\Domains\Coordination\Models;

use App\Domains\Command\Models\Command;
use App\Domains\Command\Models\CommandIncident;
use App\Domains\Coordination\Support\Enums\MutualAidStatus;
use App\Domains\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An inter-agency mutual-aid request: a requesting command asks a responding
 * command to assist on a shared command incident. Mirrors Omni's mutualaid.js
 * requestAssistance flow.
 *
 * PLATFORM-scoped: national/cross-tenant, no organization_id.
 */
final class MutualAidRequest extends Model
{
    use HasUuid;

    protected $fillable = [
        'command_incident_id',
        'requesting_command_id',
        'responding_command_id',
        'status',
        'message',
        'requested_by',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MutualAidStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CommandIncident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(CommandIncident::class, 'command_incident_id');
    }

    /**
     * @return BelongsTo<Command, $this>
     */
    public function requestingCommand(): BelongsTo
    {
        return $this->belongsTo(Command::class, 'requesting_command_id');
    }

    /**
     * @return BelongsTo<Command, $this>
     */
    public function respondingCommand(): BelongsTo
    {
        return $this->belongsTo(Command::class, 'responding_command_id');
    }
}
