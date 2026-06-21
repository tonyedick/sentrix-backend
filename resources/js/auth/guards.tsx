import type { ReactNode } from 'react';
import { Navigate, useParams } from 'react-router-dom';
import { useAuth } from '@/auth/AuthProvider';
import { FullPageSpinner } from '@/components/ui/Spinner';

/** Redirects to /login when there is no authenticated user. */
export function RequireAuth({ children }: { children: ReactNode }) {
    const { user, isLoading } = useAuth();
    if (isLoading) return <FullPageSpinner />;
    if (!user) return <Navigate to="/login" replace />;
    return <>{children}</>;
}

/**
 * Validates the {org} route param against the user's memberships (client-side
 * cross-org isolation; the server enforces too). Redirects unknown orgs to the
 * user's default org dashboard.
 */
export function RequireOrgAccess({ children }: { children: ReactNode }) {
    const { user, activeOrgId } = useAuth();
    const { org } = useParams();
    const orgs = user?.organizations ?? [];
    const isSuperAdmin = user?.roles?.includes('super-admin') ?? false;

    const belongs = isSuperAdmin || orgs.some((o) => o.id === org);
    if (!belongs) {
        return activeOrgId ? <Navigate to={`/${activeOrgId}/dashboard`} replace /> : <Navigate to="/login" replace />;
    }
    return <>{children}</>;
}

/** Guards a route by permission; falls back to the org dashboard. */
export function RequirePermission({ permission, children }: { permission: string; children: ReactNode }) {
    const { can } = useAuth();
    const { org } = useParams();
    if (!can(permission)) {
        return <Navigate to={`/${org}/dashboard`} replace />;
    }
    return <>{children}</>;
}
