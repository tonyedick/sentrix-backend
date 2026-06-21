import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { Organization, Member } from '@/types/api';

export function useOrganization(org: string) {
    return useQuery({
        queryKey: ['org', org],
        queryFn: async () => (await api.get<Organization>(`/organizations/${org}`)).data,
    });
}

export function useMembers(org: string) {
    return useQuery({
        queryKey: ['org-members', org],
        queryFn: async () => (await api.get<Member[]>(`/organizations/${org}/members`)).data,
    });
}
