import { createContext, useContext, useCallback, useEffect } from 'react';
import type { ReactNode } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api, AUTH_EXPIRED_EVENT } from '@/lib/api';
import type { CurrentUser } from '@/types/api';

interface AuthContextValue {
    user: CurrentUser | null;
    isLoading: boolean;
    /** Active organization id (route may override; this is the user default). */
    activeOrgId: string | null;
    can: (permission: string) => boolean;
    canAny: (permissions: string[]) => boolean;
    refresh: () => Promise<unknown>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export const meQueryKey = ['me'] as const;

async function fetchMe(): Promise<CurrentUser> {
    const res = await api.get<CurrentUser>('/auth/me', { with_permissions: true });
    return res.data;
}

export function AuthProvider({ children }: { children: ReactNode }) {
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery({
        queryKey: meQueryKey,
        queryFn: fetchMe,
        retry: false,
        staleTime: 60_000,
    });

    // Hard sign-out signal from the API layer (any 401) clears identity.
    useEffect(() => {
        const onExpired = () => {
            queryClient.setQueryData(meQueryKey, null);
        };
        window.addEventListener(AUTH_EXPIRED_EVENT, onExpired);
        return () => window.removeEventListener(AUTH_EXPIRED_EVENT, onExpired);
    }, [queryClient]);

    const user = data ?? null;
    const permissions = user?.permissions ?? [];
    const roles = user?.roles ?? [];

    const can = useCallback(
        (permission: string) =>
            roles.includes('super-admin') || permissions.includes(permission),
        [permissions, roles],
    );
    const canAny = useCallback((perms: string[]) => perms.some((p) => can(p)), [can]);
    const refresh = useCallback(() => queryClient.invalidateQueries({ queryKey: meQueryKey }), [queryClient]);

    const value: AuthContextValue = {
        user,
        isLoading,
        activeOrgId: user?.current_organization_id ?? user?.organizations?.[0]?.id ?? null,
        can,
        canAny,
        refresh,
    };

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth must be used within <AuthProvider>');
    return ctx;
}
