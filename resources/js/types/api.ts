/*
 | TypeScript shapes mirroring the backend API Resources (the source of truth is
 | app/Domains/*/
    // Http/Resources). Kept intentionally close to the wire payloads.
//  */

export interface Envelope<T> {
    success: boolean;
    message: string;
    data: T;
    meta?: PaginationMeta;
    links?: PaginationLinks;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface PaginationLinks {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
}

export interface Paginated<T> {
    data: T[];
    meta?: PaginationMeta;
    links?: PaginationLinks;
}

// ---- Identity / Organization ------------------------------------------------

export interface Organization {
    id: string;
    name: string;
    slug: string;
    owner_id: string;
    members_count?: number;
    created_at: string | null;
}

export interface CurrentUser {
    id: string;
    name: string;
    email: string;
    email_verified: boolean;
    current_organization_id: string | null;
    current_organization?: Organization;
    organizations?: Organization[];
    roles?: string[];
    permissions?: string[];
    created_at: string | null;
}

export interface Member {
    id: string;
    name: string;
    email: string;
    roles: string[];
    joined_at?: string | null;
}

// ---- Incident ---------------------------------------------------------------

export type IncidentStatus = 'open' | 'investigating' | 'escalated' | 'resolved' | 'closed';
export type IncidentSeverity = 'low' | 'medium' | 'high' | 'critical';

export interface Incident {
    id: string;
    organization_id: string;
    emergency_id: string | null;
    opened_by: string | null;
    assigned_to: string | null;
    status: IncidentStatus;
    severity: IncidentSeverity;
    title: string;
    summary: string | null;
    opened_at: string | null;
    escalated_at: string | null;
    resolved_at: string | null;
    closed_at: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
}

export interface TimelineEntry {
    at: string | null;
    type: string;
    source: string;
    payload: Record<string, unknown>;
}

// ---- Assignment -------------------------------------------------------------

export type AssignmentStatus =
    | 'pending'
    | 'dispatching'
    | 'partially_filled'
    | 'filled'
    | 'active'
    | 'escalated'
    | 'completed'
    | 'cancelled';

export type ResponderRole = 'primary' | 'supporting';

export type AssignmentResponderStatus =
    | 'offered'
    | 'accepted'
    | 'declined'
    | 'timed_out'
    | 'en_route'
    | 'on_scene'
    | 'completed'
    | 'stood_down';

export interface AssignmentResponder {
    id: string;
    assignment_id: string;
    organization_id: string;
    responder_id: string;
    incident_id: string;
    role: ResponderRole;
    status: AssignmentResponderStatus;
    attempt: number;
    assigned_by: string | null;
    offered_at: string | null;
    accepted_at: string | null;
    en_route_at: string | null;
    on_scene_at: string | null;
    completed_at: string | null;
    released_at: string | null;
    decline_reason: string | null;
    outcome: string | null;
    created_at: string | null;
}

export interface Assignment {
    id: string;
    organization_id: string;
    incident_id: string;
    status: AssignmentStatus;
    dispatch_mode: string;
    required_primary: number;
    required_supporting: number;
    primary_assignment_responder_id: string | null;
    escalation_level: number;
    acceptance_deadline_at: string | null;
    opened_by: string | null;
    responders?: AssignmentResponder[];
    created_at: string | null;
    updated_at: string | null;
}

/** Payload of GET /incidents/{id} — enriched detail. */
export interface IncidentDetail {
    incident: Incident;
    assignment: Assignment | null;
    timeline: TimelineEntry[];
}

// ---- Responder --------------------------------------------------------------

export type ResponderStatus =
    | 'available'
    | 'unavailable'
    | 'on_assignment'
    | 'en_route'
    | 'on_scene'
    | 'off_duty'
    | 'suspended';

export interface Responder {
    id: string;
    organization_id: string;
    user_id: string;
    status: ResponderStatus;
    on_duty: boolean;
    assignable: boolean;
    last_lat: number | null;
    last_lng: number | null;
    last_seen_at: string | null;
    current_assignment_id?: string | null;
    current_assignment?: ResponderAssignment | null;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
    updated_at: string | null;
}

/** Compact incident summary embedded in a responder's assignment line. */
export interface ResponderAssignmentIncident {
    id: string;
    title: string;
    status: IncidentStatus;
    severity: IncidentSeverity;
}

/** A responder's participation in an assignment (current or historical). */
export interface ResponderAssignment {
    id: string;
    assignment_id: string;
    incident_id: string;
    role: ResponderRole;
    status: AssignmentResponderStatus;
    attempt: number;
    offered_at: string | null;
    accepted_at: string | null;
    en_route_at: string | null;
    on_scene_at: string | null;
    completed_at: string | null;
    released_at: string | null;
    decline_reason: string | null;
    outcome: string | null;
    created_at: string | null;
    incident?: ResponderAssignmentIncident | null;
}

// ---- Emergency ---------------------------------------------------------------

export type EmergencyStatus = 'triggered' | 'acknowledged' | 'resolved' | 'cancelled';

export interface Emergency {
    id: string;
    organization_id: string;
    user_id: string | null;
    trip_id: string | null;
    status: EmergencyStatus;
    severity: IncidentSeverity;
    message: string | null;
    location: { lat: number | null; lng: number | null };
    triggered_at: string | null;
    acknowledged_at: string | null;
    acknowledged_by: string | null;
    resolved_at: string | null;
    resolved_by: string | null;
    cancelled_at: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
}

// ---- Notifications -----------------------------------------------------------

export interface NotificationItem {
    id: string;
    type: string;
    payload: Record<string, unknown>;
    read: boolean;
    read_at: string | null;
    created_at: string | null;
}

export interface Skill {
    id: string;
    organization_id: string;
    code: string;
    name: string;
    proficiency?: string | null;
}

export type CertificationStatus = 'valid' | 'expiring' | 'expired' | 'revoked' | 'pending';

export interface ResponderCertification {
    id: string;
    responder_id: string;
    organization_id: string;
    name: string;
    authority: string | null;
    issued_at: string | null;
    expires_at: string | null;
    status: CertificationStatus;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
}

export interface ResponderLocation {
    id: string;
    responder_id: string;
    lat: number;
    lng: number;
    accuracy: number | null;
    speed: number | null;
    heading: number | null;
    recorded_at: string | null;
}

export type DutyShiftStatus = 'scheduled' | 'active' | 'completed' | 'cancelled';

export interface DutyShift {
    id: string;
    responder_id: string;
    organization_id: string;
    starts_at: string | null;
    ends_at: string | null;
    status: DutyShiftStatus;
    source: string | null;
    created_at: string | null;
}
