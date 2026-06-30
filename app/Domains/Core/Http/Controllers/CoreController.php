<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Controllers;

use App\Domains\Core\DTOs\CoreEventData;
use App\Domains\Core\Events\CoreEventReceived;
use App\Domains\Core\Http\Requests\CoreEventRequest;
use App\Domains\Core\Services\CoreClient;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Emergency\Support\Enums\EmergencyStatus;
use App\Domains\Incident\Models\Incident;
use App\Domains\Incident\Support\Enums\IncidentSeverity;
use App\Domains\Incident\Support\Enums\IncidentStatus;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\Models\Responder;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Sentrix Core bridge: a thin, fail-safe HTTP proxy + broadcast surface
 * between Laravel and the external Python Core agent. Core being offline must
 * NEVER 500 a Laravel request — every action degrades gracefully.
 */
final class CoreController extends Controller
{
    /**
     * Bytes pulled per read while pumping Core's SSE body to the caller.
     */
    private const STREAM_CHUNK_BYTES = 8192;

    public function __construct(private readonly CoreClient $core) {}

    /**
     * POST /api/v1/core/chat — proxy a chat turn to Core and stream its SSE back.
     *
     * FAIL-SAFE: if Core is unconfigured or unreachable, return a valid (200)
     * text/event-stream carrying a minimal offline fallback, mirroring the Go
     * mock's graceful degrade — never a 500.
     */
    public function chat(Request $request): StreamedResponse
    {
        $user = $request->user();
        $body = $this->buildChatBody($request, $user);

        $stream = $this->core->isConfigured()
            ? $this->core->chatStream($body, $user)
            : null;

        $callback = function () use ($stream): void {
            if ($stream === null) {
                $this->emitOfflineSse();

                return;
            }

            try {
                while (! $stream->eof()) {
                    $chunk = $stream->read(self::STREAM_CHUNK_BYTES);

                    if ($chunk === '') {
                        break;
                    }

                    echo $chunk;
                    $this->flush();
                }
            } catch (\Throwable) {
                // Mid-stream failure: close out with a valid terminator so the
                // client's SSE parser is never left hanging.
                $this->emitOfflineSse();
            }
        };

        return response()->stream($callback, Response::HTTP_OK, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * POST /api/v1/core/act — JSON proxy of a confirmed tool action.
     *
     * FAIL-SAFE: if Core is unconfigured or errors, return an explicit "offline"
     * payload — never fabricate success for a (potentially high-stakes) action.
     */
    public function act(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $body */
        $body = $request->all();

        $result = $this->core->act($body, $request->user());

        if ($result === null) {
            return response()->json(['data' => [
                'ok' => false,
                'message' => 'Core is offline',
                '_simulated' => true,
            ]]);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * POST /api/v1/core/events — a product/detection posts an event. Broadcast a
     * proactive alert to the org's private channel. Service-token authed (not a
     * user). Returns 202 Accepted.
     */
    public function events(CoreEventRequest $request): JsonResponse
    {
        $event = CoreEventData::fromRequest($request);

        $organizationId = $this->resolveOrganizationId($event->org);

        event(new CoreEventReceived($event, $organizationId));

        return response()->json(['data' => ['accepted' => true]], Response::HTTP_ACCEPTED);
    }

    /**
     * GET /api/v1/core/command-center — read-only situational snapshot for the
     * platform (HQ) view. No state change, no event, no Resource: just a plain
     * `data` array of counts (cf. the skill's computed-action pattern).
     *
     * TODO: gate on the `command_center` / `monitor` roles once those exist;
     * for now this is SuperAdmin-only (platform read).
     */
    public function commandCenter(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), Response::HTTP_FORBIDDEN);

        $openIncidentsBySeverity = [];
        foreach (IncidentSeverity::values() as $severity) {
            $openIncidentsBySeverity[$severity] = Incident::query()
                ->whereNotIn('status', [IncidentStatus::Resolved->value, IncidentStatus::Closed->value])
                ->where('severity', $severity)
                ->count();
        }

        $activeEmergencies = Emergency::query()
            ->whereIn('status', [EmergencyStatus::Triggered->value, EmergencyStatus::Acknowledged->value])
            ->count();

        $onDutyResponders = Responder::query()
            ->where('on_duty', true)
            ->count();

        return response()->json(['data' => [
            'open_incidents_by_severity' => $openIncidentsBySeverity,
            'open_incidents_total' => array_sum($openIncidentsBySeverity),
            'active_emergencies' => $activeEmergencies,
            'on_duty_responders' => $onDutyResponders,
        ]]);
    }

    /**
     * Translate the client's chat payload into the shape Core expects:
     *
     *   { messages: [{ role, content }], system?, context: { session: {...}, lang } }
     *
     * The dashboard sends a single `{ message, organization_id }` turn; Core's
     * FastAPI validates a `messages[]` array + a `context.session` (user/org/role),
     * so we adapt here rather than leaking Core's contract to the client. A client
     * that already sends a `messages` array (conversation history) is passed through.
     *
     * @return array<string, mixed>
     */
    private function buildChatBody(Request $request, User $user): array
    {
        $messages = $request->input('messages');

        if (! is_array($messages) || $messages === []) {
            $messages = [[
                'role' => 'user',
                'content' => (string) $request->input('message', ''),
            ]];
        }

        $org = $request->input('organization_id') ?? $request->header('X-Organization');

        $session = array_filter([
            'user' => (string) $user->getKey(),
            'org' => $org,
            'role' => $user->getRoleNames()->first(),
        ], static fn ($value): bool => $value !== null && $value !== '');

        $body = [
            'messages' => $messages,
            'context' => [
                'session' => $session,
                'lang' => (string) $request->input('lang', 'en'),
            ],
        ];

        if ($request->filled('system')) {
            $body['system'] = (string) $request->input('system');
        }

        return $body;
    }

    /**
     * Resolve the posted `org` (an external identifier — UUID or slug) to an
     * organization UUID for channel routing. Falls back to the raw value when no
     * organization matches, so the bridge stays broadcast-only and never fails
     * on an unknown tenant.
     */
    private function resolveOrganizationId(string $org): string
    {
        // `org` may be a UUID (the org id) or a human slug. Only compare against
        // the uuid `id` column when the value is actually a UUID — otherwise
        // Postgres rejects a non-uuid string with "invalid input syntax for type
        // uuid". Non-uuid values are matched by slug.
        $organization = Organization::query()
            ->when(
                Str::isUuid($org),
                fn ($query) => $query->where('id', $org),
                fn ($query) => $query->where('slug', $org),
            )
            ->first();

        return $organization !== null ? (string) $organization->getKey() : $org;
    }

    /**
     * Emit the minimal valid SSE fallback the mobile app + Core already speak:
     * one text delta, then the [DONE] terminator.
     */
    private function emitOfflineSse(): void
    {
        echo 'data: '.json_encode(['text' => 'The assistant is offline right now.'])."\n\n";
        echo "data: [DONE]\n\n";
        $this->flush();
    }

    /**
     * Push buffered output to the client without tearing down the buffer stack
     * (so SSE deltas arrive promptly in production, and output capture in tests
     * is left intact).
     */
    private function flush(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }
}
