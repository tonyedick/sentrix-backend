import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { Emergency, Paginated } from '@/types/api';

export interface EmergencyFilters {
    status?: string;
    severity?: string;
    page?: number;
}

export const emergencyKeys = {
    list: (org: string, filters: EmergencyFilters) => ['emergencies', org, filters] as const,
    detail: (org: string, id: string) => ['emergency', org, id] as const,
};

function base(org: string) {
    return `/organizations/${org}/emergencies`;
}

export function useEmergencies(org: string, filters: EmergencyFilters) {
    return useQuery({
        queryKey: emergencyKeys.list(org, filters),
        queryFn: async () => {
            const res = await api.get<Emergency[]>(base(org), { ...filters });
            return { data: res.data, meta: res.meta, links: res.links } as Paginated<Emergency>;
        },
    });
}

export function useEmergency(org: string, id: string) {
    return useQuery({
        queryKey: emergencyKeys.detail(org, id),
        queryFn: async () => (await api.get<Emergency>(`${base(org)}/${id}`)).data,
    });
}

type Action = 'acknowledge' | 'resolve' | 'cancel';

export function useEmergencyAction(org: string, id: string) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { action: Action; resolution?: string }) =>
            (await api.post<Emergency>(`${base(org)}/${id}/${input.action}`, input.resolution ? { resolution: input.resolution } : undefined)).data,
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: emergencyKeys.detail(org, id) });
            qc.invalidateQueries({ queryKey: ['emergencies', org] });
        },
    });
}
