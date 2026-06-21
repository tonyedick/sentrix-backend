import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';
import type {
    IncidentStatus,
    AssignmentStatus,
    AssignmentResponderStatus,
    ResponderStatus,
    EmergencyStatus,
} from '@/types/api';

export type Tone = 'info' | 'success' | 'warn' | 'danger' | 'neutral' | 'accent';

const toneClass: Record<Tone, string> = {
    info: 'bg-status-info/15 text-status-info ring-status-info/25',
    success: 'bg-status-success/15 text-status-success ring-status-success/25',
    warn: 'bg-status-warn/15 text-status-warn ring-status-warn/25',
    danger: 'bg-status-danger/15 text-status-danger ring-status-danger/25',
    neutral: 'bg-content-muted/15 text-content-secondary ring-content-muted/25',
    accent: 'bg-accent/15 text-accent ring-accent/25',
};

export function Badge({ tone = 'neutral', children, className }: { tone?: Tone; children: ReactNode; className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium capitalize ring-1 ring-inset',
                toneClass[tone],
                className,
            )}
        >
            {children}
        </span>
    );
}

// ---- status → tone maps (single source of truth for color semantics) --------

export const incidentStatusTone: Record<IncidentStatus, Tone> = {
    open: 'warn',
    investigating: 'info',
    escalated: 'danger',
    resolved: 'success',
    closed: 'neutral',
};

export const assignmentStatusTone: Record<AssignmentStatus, Tone> = {
    pending: 'warn',
    dispatching: 'info',
    partially_filled: 'info',
    filled: 'success',
    active: 'success',
    escalated: 'danger',
    completed: 'neutral',
    cancelled: 'neutral',
};

export const responderStatusTone: Record<ResponderStatus, Tone> = {
    available: 'success',
    en_route: 'info',
    on_scene: 'info',
    on_assignment: 'info',
    unavailable: 'warn',
    off_duty: 'neutral',
    suspended: 'danger',
};

export const lineStatusTone: Record<AssignmentResponderStatus, Tone> = {
    offered: 'warn',
    accepted: 'success',
    en_route: 'info',
    on_scene: 'info',
    completed: 'neutral',
    declined: 'danger',
    timed_out: 'danger',
    stood_down: 'neutral',
};

export const emergencyStatusTone: Record<EmergencyStatus, Tone> = {
    triggered: 'danger',
    acknowledged: 'warn',
    resolved: 'success',
    cancelled: 'neutral',
};

function label(value: string): string {
    return value.replace(/_/g, ' ');
}

export function IncidentStatusPill({ status }: { status: IncidentStatus }) {
    return <Badge tone={incidentStatusTone[status]}>{label(status)}</Badge>;
}
export function AssignmentStatusPill({ status }: { status: AssignmentStatus }) {
    return <Badge tone={assignmentStatusTone[status]}>{label(status)}</Badge>;
}
export function ResponderStatusPill({ status }: { status: ResponderStatus }) {
    return <Badge tone={responderStatusTone[status]}>{label(status)}</Badge>;
}
export function LineStatusPill({ status }: { status: AssignmentResponderStatus }) {
    return <Badge tone={lineStatusTone[status]}>{label(status)}</Badge>;
}
export function EmergencyStatusPill({ status }: { status: EmergencyStatus }) {
    return <Badge tone={emergencyStatusTone[status]}>{label(status)}</Badge>;
}
