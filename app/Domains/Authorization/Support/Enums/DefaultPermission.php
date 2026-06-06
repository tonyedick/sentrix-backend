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

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
