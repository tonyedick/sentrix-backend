import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type {
    Responder,
    Paginated,
    ResponderStatus,
    ResponderCertification,
    ResponderLocation,
    ResponderAssignment,
    DutyShift,
    Skill,
} from '@/types/api';

export interface ResponderFilters {
    status?: string;
    assignable?: boolean;
    page?: number;
}

export const responderKeys = {
    list: (org: string, filters: ResponderFilters) => ['responders', org, filters] as const,
    detail: (org: string, id: string) => ['responder', org, id] as const,
    certs: (org: string, id: string) => ['responder-certs', org, id] as const,
    locations: (org: string, id: string) => ['responder-locations', org, id] as const,
    shifts: (org: string, id: string) => ['responder-shifts', org, id] as const,
    skills: (org: string, id: string) => ['responder-skills', org, id] as const,
    assignments: (org: string, id: string) => ['responder-assignments', org, id] as const,
};

export function useResponders(org: string, filters: ResponderFilters) {
    return useQuery({
        queryKey: responderKeys.list(org, filters),
        queryFn: async () => {
            const res = await api.get<Responder[]>(`/organizations/${org}/responders`, { ...filters });
            return { data: res.data, meta: res.meta, links: res.links } as Paginated<Responder>;
        },
    });
}

export function useResponder(org: string, id: string) {
    return useQuery({
        queryKey: responderKeys.detail(org, id),
        queryFn: async () => (await api.get<Responder>(`/organizations/${org}/responders/${id}`)).data,
    });
}

export function useResponderCertifications(org: string, id: string) {
    return useQuery({
        queryKey: responderKeys.certs(org, id),
        queryFn: async () => (await api.get<ResponderCertification[]>(`/organizations/${org}/responders/${id}/certifications`)).data,
    });
}

export function useResponderLocations(org: string, id: string) {
    return useQuery({
        queryKey: responderKeys.locations(org, id),
        queryFn: async () => {
            const res = await api.get<ResponderLocation[]>(`/organizations/${org}/responders/${id}/locations`);
            return res.data;
        },
    });
}

export function useResponderShifts(org: string, id: string) {
    return useQuery({
        queryKey: responderKeys.shifts(org, id),
        queryFn: async () => {
            const res = await api.get<DutyShift[]>(`/organizations/${org}/responders/${id}/shifts`);
            return res.data;
        },
    });
}

/** A responder's assigned skills (with proficiency). */
export function useResponderSkills(org: string, id: string) {
    return useQuery({
        queryKey: responderKeys.skills(org, id),
        queryFn: async () => (await api.get<Skill[]>(`/organizations/${org}/responders/${id}/skills`)).data,
    });
}

/** A responder's assignment participation — current + history (newest first). */
export function useResponderAssignments(org: string, id: string) {
    return useQuery({
        queryKey: responderKeys.assignments(org, id),
        queryFn: async () => {
            const res = await api.get<ResponderAssignment[]>(`/organizations/${org}/responders/${id}/assignments`);
            return { data: res.data, meta: res.meta, links: res.links } as Paginated<ResponderAssignment>;
        },
    });
}

/** Org skill catalogue (GET /skills) — reference list. */
export function useSkillCatalog(org: string) {
    return useQuery({
        queryKey: ['skills', org],
        queryFn: async () => (await api.get<Skill[]>(`/organizations/${org}/skills`)).data,
        staleTime: 5 * 60_000,
    });
}

/**
 * Optimistic status change: patches the responder detail + list caches, rolls
 * back on error. Used for availability/duty management.
 */
export function useUpdateResponderStatus(org: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { responderId: string; status: ResponderStatus }) =>
            (await api.post<Responder>(`/organizations/${org}/responders/${input.responderId}/status`, { status: input.status })).data,

        onMutate: async ({ responderId, status }) => {
            const detailKey = responderKeys.detail(org, responderId);
            await qc.cancelQueries({ queryKey: detailKey });
            const previous = qc.getQueryData<Responder>(detailKey);
            if (previous) {
                qc.setQueryData<Responder>(detailKey, { ...previous, status });
            }
            return { previous, detailKey };
        },
        onError: (_err, _vars, context) => {
            if (context?.previous) qc.setQueryData(context.detailKey, context.previous);
        },
        onSettled: (_data, _err, { responderId }) => {
            qc.invalidateQueries({ queryKey: responderKeys.detail(org, responderId) });
            qc.invalidateQueries({ queryKey: ['responders', org] });
        },
    });
}

/** Verify a certification (responders.manage). */
export function useVerifyCertification(org: string, responderId: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (certificationId: string) =>
            (await api.post(`/organizations/${org}/responders/${responderId}/certifications/${certificationId}/verify`)).data,
        onSuccess: () => qc.invalidateQueries({ queryKey: responderKeys.certs(org, responderId) }),
    });
}
