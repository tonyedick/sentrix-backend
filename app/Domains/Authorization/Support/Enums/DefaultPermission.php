<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Support\Enums;

/**
 * The global permission catalogue. Permissions are guard-global definitions;
 * roles (which are organization-scoped) are what bundle them per tenant.
 *
 * Record-level constraints (e.g. "a field user may only update their own trip")
 * are enforced in services, not encoded here — these are coarse-grained,
 * organization-wide abilities.
 */
enum DefaultPermission: string
{
    // Organization
    case OrganizationView = 'organization.view';
    case OrganizationUpdate = 'organization.update';
    case OrganizationDelete = 'organization.delete';

    // Members
    case MembersView = 'members.view';
    case MembersInvite = 'members.invite';
    case MembersUpdate = 'members.update';
    case MembersRemove = 'members.remove';

    // Roles
    case RolesManage = 'roles.manage';

    // Audit trail
    case AuditView = 'audit.view';

    // Trips
    case TripsView = 'trips.view';
    case TripsCreate = 'trips.create';
    case TripsUpdate = 'trips.update';
    case TripsCancel = 'trips.cancel';

    // Emergencies
    case EmergenciesView = 'emergencies.view';
    case EmergenciesTrigger = 'emergencies.trigger';
    case EmergenciesAcknowledge = 'emergencies.acknowledge';
    case EmergenciesResolve = 'emergencies.resolve';

    // Incidents
    case IncidentsView = 'incidents.view';
    case IncidentsCreate = 'incidents.create';
    case IncidentsUpdate = 'incidents.update';
    case IncidentsEscalate = 'incidents.escalate';
    case IncidentsResolve = 'incidents.resolve';

    // Location tracking
    case TrackingIngest = 'tracking.ingest';
    case TrackingView = 'tracking.view';

    // Responders
    case RespondersView = 'responders.view';
    case RespondersManage = 'responders.manage';
    case RespondersAssign = 'responders.assign';
    case RespondersSelf = 'responders.self';

    // Duty scheduling
    case SchedulesManage = 'schedules.manage';

    // Assignments (incident dispatch coordination)
    case AssignmentsView = 'assignments.view';
    case AssignmentsCreate = 'assignments.create';
    case AssignmentsDispatch = 'assignments.dispatch';
    case AssignmentsReassign = 'assignments.reassign';
    case AssignmentsCancel = 'assignments.cancel';
    case AssignmentsOverride = 'assignments.override';

    // Access management — visitor passes
    case PassesView = 'passes.view';
    case PassesIssue = 'passes.issue';
    case PassesManage = 'passes.manage';

    // Access management — gate
    case GateScan = 'gate.scan';
    case GateView = 'gate.view';
    case GateLog = 'gate.log';

    // Hardware device registry
    case HardwareView = 'hardware.view';
    case HardwareRegister = 'hardware.register';
    case HardwareResync = 'hardware.resync';
    case HardwareDiagnose = 'hardware.diagnose';

    // Insurance — risk, premium & claims
    case InsuranceRisk = 'insurance.risk';
    case InsuranceQuote = 'insurance.quote';
    case InsurancePoliciesView = 'insurance.policies.view';
    case InsurancePoliciesWrite = 'insurance.policies.write';
    case InsuranceClaimsView = 'insurance.claims.view';
    case InsuranceClaimsFile = 'insurance.claims.file';
    case InsuranceClaimsAdjust = 'insurance.claims.adjust';

    // Evidence — forensic vault
    case EvidenceView = 'evidence.view';
    case EvidenceIngest = 'evidence.ingest';
    case EvidenceHold = 'evidence.hold';

    // Retention — storage lifecycle (Evidence vault aging, archive, purge)
    case StorageView = 'storage.view';
    case RetentionManage = 'retention.manage';
    case RetentionArchive = 'retention.archive';
    case RetentionPurge = 'retention.purge';

    // Intel — read-only reporting & analytics (aggregates across domains)
    case IntelView = 'intel.view';
    case IntelExport = 'intel.export';

    // Webhooks — outbound partner-integration registry
    case WebhooksManage = 'webhooks.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
