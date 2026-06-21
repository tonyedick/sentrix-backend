import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { Incident, IncidentDetail, IncidentStatus, Paginated, ResponderRole } from '@/types/api';

export interface IncidentFilters {
    status?: string;
    severity?: string;
    page?: number;
}

export const incidentKeys = {
    list: (org: string, filters: IncidentFilters) => ['incidents', org, filters] as const,
    detail: (org: string, id: string) => ['incident', org, id] as const,
};

function base(org: string) {
    return `/organizations/${org}/incidents`;
}

export function useIncidents(org: string, filters: IncidentFilters) {
    return useQuery({
        queryKey: incidentKeys.list(org, filters),
        queryFn: async () => {
            const res = await api.get<Incident[]>(base(org), { ...filters });
            return { data: res.data, meta: res.meta, links: res.links } as Paginated<Incident>;
        },
    });
}

export function useIncident(org: string, id: string) {
    return useQuery({
        queryKey: incidentKeys.detail(org, id),
        queryFn: async () => (await api.get<IncidentDetail>(`${base(org)}/${id}`)).data,
    });
}

export function useOpenIncident(org: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (payload: { title: string; severity?: string; summary?: string }) =>
            (await api.post<Incident>(base(org), payload)).data,
        onSuccess: () => qc.invalidateQueries({ queryKey: ['incidents', org] }),
    });
}

type TransitionAction = 'investigate' | 'escalate' | 'resolve' | 'close';

const actionToStatus: Record<TransitionAction, IncidentStatus> = {
    investigate: 'investigating',
    escalate: 'escalated',
    resolve: 'resolved',
    close: 'closed',
};

const actionToTimestamp: Partial<Record<TransitionAction, keyof Incident>> = {
    escalate: 'escalated_at',
    resolve: 'resolved_at',
    close: 'closed_at',
};

/**
 * Status transitions are optimistic: the detail cache flips immediately and
 * rolls back if the server rejects the move (mirrors the guarded backend state
 * machine). The list is invalidated on settle so the board reconciles.
 */
export function useIncidentTransition(org: string, incidentId: string) {
    const qc = useQueryClient();
    const detailKey = incidentKeys.detail(org, incidentId);

    return useMutation({
        mutationFn: async (action: TransitionAction) =>
            (await api.post<Incident>(`${base(org)}/${incidentId}/${action}`)).data,

        onMutate: async (action) => {
            await qc.cancelQueries({ queryKey: detailKey });
            const previous = qc.getQueryData<IncidentDetail>(detailKey);
            if (previous) {
                const nowIso = new Date().toISOString();
                const tsField = actionToTimestamp[action];
                qc.setQueryData<IncidentDetail>(detailKey, {
                    ...previous,
                    incident: {
                        ...previous.incident,
                        status: actionToStatus[action],
                        ...(tsField ? { [tsField]: nowIso } : {}),
                    },
                });
            }
            return { previous };
        },

        onError: (_err, _action, context) => {
            if (context?.previous) qc.setQueryData(detailKey, context.previous);
        },

        onSettled: () => {
            qc.invalidateQueries({ queryKey: detailKey });
            qc.invalidateQueries({ queryKey: ['incidents', org] });
        },
    });
}

// ---- Dispatch (assignment) actions used from the incident detail ------------

export function useEnsureAssignment(org: string, incidentId: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async () =>
            (await api.post<{ id: string }>(`/organizations/${org}/assignments`, { incident_id: incidentId })).data,
        onSuccess: () => qc.invalidateQueries({ queryKey: incidentKeys.detail(org, incidentId) }),
    });
}

export function useOfferResponder(org: string, incidentId: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { assignmentId: string; responder_id: string; role: ResponderRole }) =>
            (
                await api.post(`/organizations/${org}/assignments/${input.assignmentId}/responders`, {
                    responder_id: input.responder_id,
                    role: input.role,
                })
            ).data,
        onSuccess: () => qc.invalidateQueries({ queryKey: incidentKeys.detail(org, incidentId) }),
    });
}

export function useCancelAssignment(org: string, incidentId: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (assignmentId: string) =>
            (await api.post(`/organizations/${org}/assignments/${assignmentId}/cancel`)).data,
        onSuccess: () => qc.invalidateQueries({ queryKey: incidentKeys.detail(org, incidentId) }),
    });
}
