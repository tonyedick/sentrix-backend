import type { ReactNode } from 'react';
import { NavLink, useParams } from 'react-router-dom';
import { useAuth } from '@/auth/AuthProvider';
import { useUiStore } from '@/state/uiStore';
import { cn } from '@/lib/cn';
import { GridIcon, AlertIcon, UsersIcon, SettingsIcon, SirenIcon, EscalationIcon, BellIcon } from '@/components/layout/icons';

interface NavItem {
    to: string;
    label: string;
    Icon: (props: { className?: string }) => ReactNode;
    permission?: string;
}

export function LeftRail() {
    const { org } = useParams();
    const { can } = useAuth();
    const navDrawerOpen = useUiStore((s) => s.navDrawerOpen);
    const setNavDrawerOpen = useUiStore((s) => s.setNavDrawerOpen);

    const items: NavItem[] = [
        { to: `/${org}/dashboard`, label: 'Dashboard', Icon: GridIcon, permission: 'incidents.view' },
        { to: `/${org}/incidents`, label: 'Incidents', Icon: AlertIcon, permission: 'incidents.view' },
        { to: `/${org}/emergencies`, label: 'Emergencies', Icon: SirenIcon, permission: 'emergencies.view' },
        { to: `/${org}/escalations`, label: 'Escalations', Icon: EscalationIcon, permission: 'incidents.view' },
        { to: `/${org}/responders`, label: 'Responders', Icon: UsersIcon, permission: 'responders.view' },
        { to: `/${org}/notifications`, label: 'Notifications', Icon: BellIcon },
    ];
    const settings: NavItem = { to: `/${org}/settings`, label: 'Settings', Icon: SettingsIcon };

    const renderItem = ({ to, label, Icon, permission }: NavItem) => {
        if (permission && !can(permission)) return null;
        return (
            <NavLink
                key={to}
                to={to}
                onClick={() => setNavDrawerOpen(false)}
                className={({ isActive }) =>
                    cn(
                        'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                        isActive
                            ? 'bg-surface-2 text-content-primary ring-1 ring-inset ring-border-default'
                            : 'text-content-secondary hover:bg-surface-2 hover:text-content-primary',
                    )
                }
            >
                {({ isActive }) => (
                    <>
                        <Icon className={cn('size-[18px]', isActive && 'text-accent')} />
                        {label}
                    </>
                )}
            </NavLink>
        );
    };

    return (
        <>
            {navDrawerOpen && (
                <div className="fixed inset-0 z-20 bg-black/50 lg:hidden" onClick={() => setNavDrawerOpen(false)} />
            )}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-30 flex w-60 flex-col gap-1 border-r border-border-subtle bg-surface-1 px-3 pt-16 transition-transform lg:static lg:translate-x-0 lg:pt-3',
                    navDrawerOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <nav className="flex flex-1 flex-col gap-1">
                    {items.map(renderItem)}
                    <div className="my-2 border-t border-border-subtle" />
                    {renderItem(settings)}
                </nav>
                <p className="px-3 py-3 text-[10px] uppercase tracking-wider text-content-muted">Phase 1 · Operations</p>
            </aside>
        </>
    );
}
