<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Support\Enums;

/**
 * The default organization-scoped roles provisioned for every new organization,
 * together with the permissions each one grants. RoleService materialises these
 * as team-scoped Spatie roles when an organization is created.
 *
 * Platform-global roles (e.g. SuperAdmin) live in {@see SystemRole}.
 */
enum OrganizationRole: string
{
    case OrganizationAdmin = 'OrganizationAdmin';
    case Dispatcher = 'Dispatcher';
    case Responder = 'Responder';
    case User = 'User';

    /**
     * Permissions granted to this role within its organization.
     *
     * The organization owner additionally receives an implicit super-grant
     * within their own organization (see AuthorizationServiceProvider), so the
     * OrganizationAdmin set intentionally excludes destructive org-level
     * abilities such as deleting the organization.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::OrganizationAdmin => [
                DefaultPermission::OrganizationView->value,
                DefaultPermission::OrganizationUpdate->value,
                DefaultPermission::MembersView->value,
                DefaultPermission::MembersInvite->value,
                DefaultPermission::MembersUpdate->value,
                DefaultPermission::MembersRemove->value,
                DefaultPermission::RolesManage->value,
                DefaultPermission::AuditView->value,
                DefaultPermission::TripsView->value,
                DefaultPermission::TripsCreate->value,
                DefaultPermission::TripsUpdate->value,
                DefaultPermission::TripsCancel->value,
                DefaultPermission::EmergenciesView->value,
                DefaultPermission::EmergenciesTrigger->value,
                DefaultPermission::EmergenciesAcknowledge->value,
                DefaultPermission::EmergenciesResolve->value,
                DefaultPermission::IncidentsView->value,
                DefaultPermission::IncidentsCreate->value,
                DefaultPermission::IncidentsUpdate->value,
                DefaultPermission::IncidentsEscalate->value,
                DefaultPermission::IncidentsResolve->value,
                DefaultPermission::TrackingIngest->value,
                DefaultPermission::TrackingView->value,
                DefaultPermission::RespondersView->value,
                DefaultPermission::RespondersManage->value,
                DefaultPermission::RespondersAssign->value,
                DefaultPermission::RespondersSelf->value,
                DefaultPermission::SchedulesManage->value,
                DefaultPermission::AssignmentsView->value,
                DefaultPermission::AssignmentsCreate->value,
                DefaultPermission::AssignmentsDispatch->value,
                DefaultPermission::AssignmentsReassign->value,
                DefaultPermission::AssignmentsCancel->value,
                DefaultPermission::AssignmentsOverride->value,
                DefaultPermission::PassesView->value,
                DefaultPermission::PassesIssue->value,
                DefaultPermission::PassesManage->value,
                DefaultPermission::GateScan->value,
                DefaultPermission::GateView->value,
                DefaultPermission::GateLog->value,
                DefaultPermission::HardwareView->value,
                DefaultPermission::HardwareRegister->value,
                DefaultPermission::HardwareResync->value,
                DefaultPermission::HardwareDiagnose->value,
                DefaultPermission::InsuranceRisk->value,
                DefaultPermission::InsuranceQuote->value,
                DefaultPermission::InsurancePoliciesView->value,
                DefaultPermission::InsurancePoliciesWrite->value,
                DefaultPermission::InsuranceClaimsView->value,
                DefaultPermission::InsuranceClaimsFile->value,
                // TODO: dedicated insurer role — claims.adjust belongs to an
                // external insurer party; granted to OrganizationAdmin until one exists.
                DefaultPermission::InsuranceClaimsAdjust->value,
                DefaultPermission::EvidenceView->value,
                DefaultPermission::EvidenceIngest->value,
                DefaultPermission::EvidenceHold->value,
                DefaultPermission::StorageView->value,
                DefaultPermission::RetentionManage->value,
                DefaultPermission::RetentionArchive->value,
                DefaultPermission::RetentionPurge->value,
                DefaultPermission::IntelView->value,
                DefaultPermission::IntelExport->value,
                DefaultPermission::WebhooksManage->value,
            ],
            // Dispatchers monitor live activity and coordinate the response.
            self::Dispatcher => [
                DefaultPermission::OrganizationView->value,
                DefaultPermission::MembersView->value,
                DefaultPermission::AuditView->value,
                DefaultPermission::TripsView->value,
                DefaultPermission::TripsUpdate->value,
                DefaultPermission::EmergenciesView->value,
                DefaultPermission::EmergenciesAcknowledge->value,
                DefaultPermission::EmergenciesResolve->value,
                DefaultPermission::IncidentsView->value,
                DefaultPermission::IncidentsCreate->value,
                DefaultPermission::IncidentsUpdate->value,
                DefaultPermission::IncidentsEscalate->value,
                DefaultPermission::IncidentsResolve->value,
                DefaultPermission::TrackingView->value,
                DefaultPermission::RespondersView->value,
                DefaultPermission::RespondersAssign->value,
                DefaultPermission::SchedulesManage->value,
                DefaultPermission::AssignmentsView->value,
                DefaultPermission::AssignmentsCreate->value,
                DefaultPermission::AssignmentsDispatch->value,
                DefaultPermission::AssignmentsReassign->value,
                DefaultPermission::AssignmentsCancel->value,
                DefaultPermission::PassesView->value,
                DefaultPermission::GateScan->value,
                DefaultPermission::GateView->value,
                DefaultPermission::GateLog->value,
                DefaultPermission::HardwareView->value,
                // Dispatchers get the read-only insurance views.
                DefaultPermission::InsuranceRisk->value,
                DefaultPermission::InsurancePoliciesView->value,
                DefaultPermission::InsuranceClaimsView->value,
                DefaultPermission::EvidenceView->value,
                DefaultPermission::StorageView->value,
                // Dispatchers live in reports — full Intel read + export.
                DefaultPermission::IntelView->value,
                DefaultPermission::IntelExport->value,
            ],
            // Responders act on emergencies and incidents in the field.
            self::Responder => [
                DefaultPermission::OrganizationView->value,
                DefaultPermission::TripsView->value,
                DefaultPermission::EmergenciesView->value,
                DefaultPermission::EmergenciesAcknowledge->value,
                DefaultPermission::EmergenciesResolve->value,
                DefaultPermission::IncidentsView->value,
                DefaultPermission::IncidentsUpdate->value,
                DefaultPermission::TrackingView->value,
                DefaultPermission::RespondersView->value,
                DefaultPermission::RespondersSelf->value,
                DefaultPermission::GateScan->value,
                DefaultPermission::GateView->value,
                // A field member can file a claim and see their org's claims.
                DefaultPermission::InsuranceClaimsFile->value,
                DefaultPermission::InsuranceClaimsView->value,
                DefaultPermission::EvidenceView->value,
            ],
            // Field users / monitored individuals run their own trips and can
            // raise an emergency. Record-level ownership is enforced in services.
            self::User => [
                DefaultPermission::OrganizationView->value,
                DefaultPermission::TripsView->value,
                DefaultPermission::TripsCreate->value,
                DefaultPermission::TripsUpdate->value,
                DefaultPermission::TripsCancel->value,
                DefaultPermission::EmergenciesView->value,
                DefaultPermission::EmergenciesTrigger->value,
                DefaultPermission::TrackingIngest->value,
                DefaultPermission::TrackingView->value,
                DefaultPermission::PassesView->value,
                DefaultPermission::PassesIssue->value,
                // A field member can file a claim and see their org's claims.
                DefaultPermission::InsuranceClaimsFile->value,
                DefaultPermission::InsuranceClaimsView->value,
            ],
        };
    }

    /**
     * The role assigned to the user who creates an organization.
     */
    public static function owner(): self
    {
        return self::OrganizationAdmin;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
