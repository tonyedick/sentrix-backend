import type { ReactNode } from 'react';
import { useAuth } from '@/auth/AuthProvider';

/**
 * Renders children only if the user holds the permission(s). UX-only gating —
 * the server remains the enforcement boundary. Hides rather than disables.
 */
export function PermissionGate({
    permission,
    anyOf,
    fallback = null,
    children,
}: {
    permission?: string;
    anyOf?: string[];
    fallback?: ReactNode;
    children: ReactNode;
}) {
    const { can, canAny } = useAuth();
    const allowed = permission ? can(permission) : anyOf ? canAny(anyOf) : true;
    return <>{allowed ? children : fallback}</>;
}
