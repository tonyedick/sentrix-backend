<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Assignment\Models\Assignment;
use App\Domains\Assignment\Models\AssignmentEvent;
use App\Domains\Assignment\Models\AssignmentResponder;
use App\Domains\Emergency\Models\Emergency;
use App\Domains\Incident\Models\Incident;
use App\Domains\Incident\Models\IncidentTimelineEntry;
use App\Domains\Organization\Models\Organization;
use App\Domains\Responder\Models\DutyShift;
use App\Domains\Responder\Models\Responder;
use App\Domains\Responder\Models\ResponderCertification;
use App\Domains\Responder\Models\ResponderLocation;
use App\Domains\Responder\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Populates the demo organization (Acme Inc, owned by test@example.com) with a
 * coherent slice of operational data so every Operations Dashboard screen shows
 * real rows: responders (with skills/certs/locations/shifts), incidents (with
 * timelines), emergencies, an active dispatch assignment, and in-app
 * notifications.
 *
 * Idempotent: a no-op once the demo org already has incidents, so `db:seed` is
 * safe to re-run. Writes rows directly (no domain events) so seeding never needs
 * Reverb or a queue worker running — the dashboard reads the same tables.
 */
final class DemoOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->where('email', 'test@example.com')->first();
        if (! $owner) {
            $this->command?->warn('DemoOperationsSeeder: test@example.com not found — run DatabaseSeeder first. Skipping.');

            return;
        }

        /** @var Organization|null $org */
        $org = $owner->organizations()->first();
        if (! $org) {
            $this->command?->warn('DemoOperationsSeeder: demo org missing. Skipping.');

            return;
        }

        if (Incident::query()->where('organization_id', $org->getKey())->exists()) {
            $this->command?->info('DemoOperationsSeeder: demo data already present. Skipping.');

            return;
        }

        // All-or-nothing: a mid-way failure rolls back fully, so a re-run never
        // collides with half-seeded rows (skills, responders, etc.).
        DB::transaction(function () use ($owner, $org): void {
            $orgId = $org->getKey();
            $now = Carbon::now();

        // ---- Skills catalogue -------------------------------------------------
        $skillDefs = [
            ['code' => 'first_aid', 'name' => 'First Aid'],
            ['code' => 'cpr', 'name' => 'CPR'],
            ['code' => 'fire_response', 'name' => 'Fire Response'],
            ['code' => 'security', 'name' => 'Security'],
            ['code' => 'driving', 'name' => 'Advanced Driving'],
        ];
        $skills = [];
        foreach ($skillDefs as $def) {
            $skills[$def['code']] = Skill::create(['organization_id' => $orgId, ...$def]);
        }

        // ---- Responders -------------------------------------------------------
        $responderDefs = [
            ['name' => 'Amara Okafor', 'status' => 'available', 'on_duty' => true, 'lat' => 6.4541, 'lng' => 3.3947, 'seen' => 1, 'skills' => ['first_aid' => 'expert', 'cpr' => 'trained']],
            ['name' => 'Tunde Bello', 'status' => 'engaged', 'on_duty' => true, 'lat' => 6.4488, 'lng' => 3.4012, 'seen' => 2, 'skills' => ['fire_response' => 'expert', 'driving' => 'trained']],
            ['name' => 'Chidinma Eze', 'status' => 'available', 'on_duty' => true, 'lat' => 6.4602, 'lng' => 3.3881, 'seen' => 4, 'skills' => ['security' => 'expert']],
            ['name' => 'Yusuf Aliyu', 'status' => 'off_duty', 'on_duty' => false, 'lat' => 6.4399, 'lng' => 3.4104, 'seen' => 240, 'skills' => ['first_aid' => 'trained']],
            ['name' => 'Ngozi Udo', 'status' => 'unavailable', 'on_duty' => false, 'lat' => 6.4655, 'lng' => 3.3799, 'seen' => 30, 'skills' => ['cpr' => 'trainee', 'driving' => 'expert']],
            ['name' => 'Emeka Nwosu', 'status' => 'available', 'on_duty' => true, 'lat' => 6.4521, 'lng' => 3.3990, 'seen' => 6, 'skills' => ['fire_response' => 'trained', 'security' => 'trained']],
        ];

        $responders = [];
        foreach ($responderDefs as $i => $def) {
            $user = User::factory()->create([
                'name' => $def['name'],
                'email' => 'responder'.($i + 1).'@sentrix.test',
            ]);

            $responder = Responder::create([
                'organization_id' => $orgId,
                'user_id' => $user->getKey(),
                'status' => $def['status'],
                'on_duty' => $def['on_duty'],
                'last_lat' => $def['lat'],
                'last_lng' => $def['lng'],
                'last_seen_at' => $now->copy()->subMinutes($def['seen']),
            ]);

            foreach ($def['skills'] as $code => $prof) {
                $responder->skills()->attach($skills[$code]->getKey(), ['proficiency' => $prof]);
            }

            ResponderCertification::create([
                'responder_id' => $responder->getKey(),
                'organization_id' => $orgId,
                'name' => 'Emergency First Response',
                'authority' => 'Red Cross',
                'issued_at' => $now->copy()->subMonths(8),
                'expires_at' => $now->copy()->addMonths(16),
                'status' => 'verified',
            ]);
            if ($i % 2 === 0) {
                ResponderCertification::create([
                    'responder_id' => $responder->getKey(),
                    'organization_id' => $orgId,
                    'name' => 'Advanced Driving Permit',
                    'authority' => 'FRSC',
                    'issued_at' => $now->copy()->subMonths(3),
                    'expires_at' => $now->copy()->addMonths(21),
                    'status' => 'pending',
                ]);
            }

            ResponderLocation::create([
                'id' => (string) Str::orderedUuid(),
                'responder_id' => $responder->getKey(),
                'organization_id' => $orgId,
                'user_id' => $user->getKey(),
                'client_fix_id' => (string) Str::uuid(),
                'lat' => $def['lat'],
                'lng' => $def['lng'],
                'accuracy' => 12.5,
                'recorded_at' => $now->copy()->subMinutes($def['seen']),
                'received_at' => $now->copy()->subMinutes($def['seen']),
            ]);

            DutyShift::create([
                'responder_id' => $responder->getKey(),
                'organization_id' => $orgId,
                'starts_at' => $now->copy()->subHours(2),
                'ends_at' => $now->copy()->addHours(6),
                'status' => $def['on_duty'] ? 'active' : 'scheduled',
                'source' => 'manual',
            ]);

            $responders[] = $responder;
        }

        // ---- Incidents + timelines -------------------------------------------
        $incidentDefs = [
            ['title' => 'Multi-vehicle collision on Third Mainland Bridge', 'severity' => 'critical', 'status' => 'investigating', 'age' => 35],
            ['title' => 'Armed robbery reported in Lekki Phase 1', 'severity' => 'high', 'status' => 'escalated', 'age' => 70],
            ['title' => 'Building fire at Computer Village', 'severity' => 'high', 'status' => 'open', 'age' => 12],
            ['title' => 'Flooding on Victoria Island access road', 'severity' => 'medium', 'status' => 'open', 'age' => 8],
            ['title' => 'Medical emergency at Ikeja City Mall', 'severity' => 'high', 'status' => 'resolved', 'age' => 320],
            ['title' => 'Stranded motorist on Lagos-Ibadan Expressway', 'severity' => 'low', 'status' => 'resolved', 'age' => 600],
            ['title' => 'Suspicious package near Marina', 'severity' => 'medium', 'status' => 'closed', 'age' => 1500],
            ['title' => 'Power line down on Awolowo Road', 'severity' => 'medium', 'status' => 'open', 'age' => 20],
        ];

        $incidents = [];
        foreach ($incidentDefs as $def) {
            $openedAt = $now->copy()->subMinutes($def['age']);
            $incident = Incident::create([
                'organization_id' => $orgId,
                'opened_by' => $owner->getKey(),
                'status' => $def['status'],
                'severity' => $def['severity'],
                'title' => $def['title'],
                'summary' => 'Reported via Sentrix monitoring. Operator triaging response.',
                'opened_at' => $openedAt,
                'escalated_at' => $def['status'] === 'escalated' ? $openedAt->copy()->addMinutes(10) : null,
                'resolved_at' => in_array($def['status'], ['resolved', 'closed'], true) ? $openedAt->copy()->addMinutes(45) : null,
                'closed_at' => $def['status'] === 'closed' ? $openedAt->copy()->addMinutes(90) : null,
            ]);

            $this->timeline($incident->getKey(), $orgId, 'incident.opened', $owner->getKey(), $openedAt, ['severity' => $def['severity']]);
            if (in_array($def['status'], ['investigating', 'escalated', 'resolved', 'closed'], true)) {
                $this->timeline($incident->getKey(), $orgId, 'incident.status_changed', $owner->getKey(), $openedAt->copy()->addMinutes(5), ['to' => 'investigating']);
            }
            if ($def['status'] === 'escalated') {
                $this->timeline($incident->getKey(), $orgId, 'incident.escalated', null, $openedAt->copy()->addMinutes(10), ['reason' => 'No responder on scene within SLA']);
            }
            if (in_array($def['status'], ['resolved', 'closed'], true)) {
                $this->timeline($incident->getKey(), $orgId, 'incident.resolved', $owner->getKey(), $openedAt->copy()->addMinutes(45), []);
            }

            $incidents[] = $incident;
        }

        // ---- Emergencies ------------------------------------------------------
        $emergencyDefs = [
            ['status' => 'triggered', 'severity' => 'critical', 'message' => 'SOS triggered — no response to call back', 'age' => 4, 'lat' => 6.4550, 'lng' => 3.3950],
            ['status' => 'acknowledged', 'severity' => 'high', 'message' => 'Panic button held during trip', 'age' => 18, 'lat' => 6.4470, 'lng' => 3.4000],
            ['status' => 'resolved', 'severity' => 'medium', 'message' => 'User confirmed safe after check-in', 'age' => 240, 'lat' => 6.4610, 'lng' => 3.3870],
            ['status' => 'cancelled', 'severity' => 'low', 'message' => 'Accidental trigger, cancelled by user', 'age' => 500, 'lat' => 6.4400, 'lng' => 3.4100],
        ];
        foreach ($emergencyDefs as $def) {
            $triggeredAt = $now->copy()->subMinutes($def['age']);
            Emergency::create([
                'organization_id' => $orgId,
                'user_id' => $owner->getKey(),
                'status' => $def['status'],
                'severity' => $def['severity'],
                'message' => $def['message'],
                'lat' => $def['lat'],
                'lng' => $def['lng'],
                'triggered_at' => $triggeredAt,
                'acknowledged_at' => in_array($def['status'], ['acknowledged', 'resolved'], true) ? $triggeredAt->copy()->addMinutes(2) : null,
                'acknowledged_by' => in_array($def['status'], ['acknowledged', 'resolved'], true) ? $owner->getKey() : null,
                'resolved_at' => $def['status'] === 'resolved' ? $triggeredAt->copy()->addMinutes(30) : null,
                'resolved_by' => $def['status'] === 'resolved' ? $owner->getKey() : null,
                'cancelled_at' => $def['status'] === 'cancelled' ? $triggeredAt->copy()->addMinutes(1) : null,
            ]);
        }

        // ---- Active dispatch assignment --------------------------------------
        $this->seedAssignment($orgId, $incidents[0], $responders[1], $owner, $now);

        // ---- In-app notifications for the operator ---------------------------
        $this->notify($owner, 'New critical incident', 'Multi-vehicle collision on Third Mainland Bridge', $now->copy()->subMinutes(35), false);
        $this->notify($owner, 'Incident escalated', 'Armed robbery in Lekki Phase 1 breached SLA', $now->copy()->subMinutes(60), false);
        $this->notify($owner, 'Responder on scene', 'Tunde Bello arrived at the collision site', $now->copy()->subMinutes(20), true);
        });

        $this->command?->info('DemoOperationsSeeder: seeded responders, incidents, emergencies, an assignment, and notifications for '.$org->name.'.');
    }

    private function timeline(string $incidentId, string $orgId, string $type, ?string $actorId, Carbon $at, array $payload): void
    {
        IncidentTimelineEntry::create([
            'organization_id' => $orgId,
            'incident_id' => $incidentId,
            'type' => $type,
            // Allowed sources (CHECK): incident | assignment | notification | ai | system.
            'source' => $actorId ? 'incident' : 'system',
            'actor_id' => $actorId,
            'payload' => $payload,
            'occurred_at' => $at,
        ]);
    }

    private function seedAssignment(string $orgId, Incident $incident, Responder $primary, User $owner, Carbon $now): void
    {
        $assignment = Assignment::create([
            'organization_id' => $orgId,
            'incident_id' => $incident->getKey(),
            'status' => 'active',
            'dispatch_mode' => 'manual',
            'required_primary' => true,
            'required_supporting' => 0,
            'opened_by' => $owner->getKey(),
            'escalation_level' => 0,
        ]);

        $line = AssignmentResponder::create([
            'assignment_id' => $assignment->getKey(),
            'organization_id' => $orgId,
            'responder_id' => $primary->getKey(),
            'incident_id' => $incident->getKey(),
            'role' => 'primary',
            'status' => 'on_scene',
            'attempt' => 1,
            'assigned_by' => $owner->getKey(),
            'offered_at' => $now->copy()->subMinutes(30),
            'accepted_at' => $now->copy()->subMinutes(28),
            'en_route_at' => $now->copy()->subMinutes(25),
            'on_scene_at' => $now->copy()->subMinutes(18),
        ]);

        $assignment->update(['primary_assignment_responder_id' => $line->getKey()]);
        // responders.current_assignment_id FKs to assignment_responders (the line), not assignments.
        $primary->update(['current_assignment_id' => $line->getKey(), 'status' => 'engaged']);

        foreach ([
            ['type' => 'assignment.opened', 'at' => 30],
            ['type' => 'assignment.responder_offered', 'at' => 30],
            ['type' => 'assignment.responder_accepted', 'at' => 28],
            ['type' => 'assignment.responder_en_route', 'at' => 25],
            ['type' => 'assignment.responder_on_scene', 'at' => 18],
        ] as $ev) {
            AssignmentEvent::create([
                'assignment_id' => $assignment->getKey(),
                'organization_id' => $orgId,
                'type' => $ev['type'],
                'actor_id' => $owner->getKey(),
                'assignment_responder_id' => $line->getKey(),
                'payload' => [],
            ]);
        }
    }

    private function notify(User $user, string $title, string $body, Carbon $at, bool $read): void
    {
        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Domains\\Notification\\Notifications\\OperationsAlert',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'data' => ['title' => $title, 'body' => $body],
            'read_at' => $read ? $at : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
