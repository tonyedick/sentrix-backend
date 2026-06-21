import { useCallback, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { usePrivateChannel } from '@/realtime/useRealtime';
import { incidentKeys } from '@/features/incidents/api';
import type { Tone } from '@/components/ui/StatusPill';

export interface ActivityItem {
    id: string;
    at: string;
    label: string;
    source: 'incident' | 'assignment';
    tone: Tone;
}

const MAX_ITEMS = 50;

// event name (without leading dot) → human label + tone
const INCIDENT_EVENTS: Record<string, { label: string; tone: Tone }> = {
    'incident.opened': { label: 'Incident opened', tone: 'info' },
    'incident.status_changed': { label: 'Status changed', tone: 'info' },
    'incident.escalated': { label: 'Incident escalated', tone: 'danger' },
    'incident.resolved': { label: 'Incident resolved', tone: 'success' },
    'incident.closed': { label: 'Incident closed', tone: 'neutral' },
};

const ASSIGNMENT_EVENTS: Record<string, { label: string; tone: Tone }> = {
    'assignment.created': { label: 'Dispatch created', tone: 'accent' },
    'assignment.responder_offered': { label: 'Responder offered', tone: 'warn' },
    'assignment.responder_accepted': { label: 'Responder accepted', tone: 'success' },
    'assignment.responder_declined': { label: 'Responder declined', tone: 'danger' },
    'assignment.responder_timed_out': { label: 'Offer timed out', tone: 'danger' },
    'assignment.responder_en_route': { label: 'Responder en route', tone: 'info' },
    'assignment.responder_on_scene': { label: 'Responder on scene', tone: 'info' },
    'assignment.responder_completed': { label: 'Responder completed', tone: 'neutral' },
    'assignment.dispatch_escalated': { label: 'Dispatch escalated', tone: 'danger' },
    'assignment.cancelled': { label: 'Dispatch cancelled', tone: 'neutral' },
    'assignment.completed': { label: 'Dispatch completed', tone: 'neutral' },
};

let seq = 0;

/**
 * Single source of realtime for the incident workspace. Subscribes once to the
 * org incidents + assignments channels, (1) invalidates the incident detail
 * cache on every relevant event and (2) accumulates an in-session activity
 * stream scoped to THIS incident (incident events match on payload.id;
 * assignment events match on payload.incident_id). Returns the activity buffer.
 */
export function useIncidentRealtime(org: string, incidentId: string): ActivityItem[] {
    const qc = useQueryClient();
    const [items, setItems] = useState<ActivityItem[]>([]);
    const detailKey = incidentKeys.detail(org, incidentId);

    const push = useCallback((label: string, source: ActivityItem['source'], tone: Tone) => {
        setItems((prev) => [
            { id: `a${++seq}`, at: new Date().toISOString(), label, source, tone },
            ...prev,
        ].slice(0, MAX_ITEMS));
    }, []);

    const invalidate = useCallback(() => qc.invalidateQueries({ queryKey: detailKey }), [qc, detailKey]);

    const incidentHandlers: Record<string, (payload: unknown) => void> = Object.fromEntries(
        Object.entries(INCIDENT_EVENTS).map(([event, meta]): [string, (payload: unknown) => void] => [
            event,
            (payload: unknown) => {
                invalidate();
                const p = payload as { id?: string };
                if (p?.id && p.id !== incidentId) return; // a different incident
                push(meta.label, 'incident', meta.tone);
            },
        ]),
    );

    const assignmentHandlers: Record<string, (payload: unknown) => void> = Object.fromEntries(
        Object.entries(ASSIGNMENT_EVENTS).map(([event, meta]): [string, (payload: unknown) => void] => [
            event,
            (payload: unknown) => {
                invalidate();
                const p = payload as { incident_id?: string };
                if (p?.incident_id && p.incident_id !== incidentId) return;
                push(meta.label, 'assignment', meta.tone);
            },
        ]),
    );

    usePrivateChannel(`organizations.${org}.incidents`, incidentHandlers);
    usePrivateChannel(`organizations.${org}.assignments`, assignmentHandlers);

    return items;
}
