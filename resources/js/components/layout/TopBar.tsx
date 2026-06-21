import { useNavigate, useParams } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { useAuth, meQueryKey } from '@/auth/AuthProvider';
import { useEcho } from '@/realtime/EchoProvider';
import { useUiStore } from '@/state/uiStore';
import { api } from '@/lib/api';
import { Logo } from '@/components/ui/Logo';
import { Button } from '@/components/ui/Button';
import { SearchIcon } from '@/components/layout/icons';
import { cn } from '@/lib/cn';

export function TopBar() {
    const { user } = useAuth();
    const { org } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { connection } = useEcho();
    const setNavDrawerOpen = useUiStore((s) => s.setNavDrawerOpen);
    const setCommandPaletteOpen = useUiStore((s) => s.setCommandPaletteOpen);

    const organizations = user?.organizations ?? [];

    async function switchOrg(id: string) {
        if (id === org) return;
        try {
            await api.post(`/organizations/${id}/switch`);
        } catch {
            /* server may not require an explicit switch; navigation still scopes the UI */
        }
        await queryClient.invalidateQueries({ queryKey: meQueryKey });
        queryClient.clear();
        navigate(`/${id}/dashboard`);
    }

    async function signOut() {
        try {
            await api.post('/auth/logout');
        } finally {
            queryClient.setQueryData(meQueryKey, null);
            queryClient.clear();
            navigate('/login');
        }
    }

    const connTone =
        connection === 'connected' ? 'bg-status-success' : connection === 'connecting' ? 'bg-status-warn' : 'bg-status-danger';

    return (
        <header className="flex h-14 items-center gap-3 border-b border-border-subtle bg-surface-1 px-4">
            <button
                className="rounded p-1.5 text-content-secondary hover:bg-surface-2 lg:hidden"
                onClick={() => setNavDrawerOpen(true)}
                aria-label="Open navigation"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                </svg>
            </button>

            <Logo size={26} withWordmark />

            {organizations.length > 0 && (
                <select
                    value={org}
                    onChange={(e) => switchOrg(e.target.value)}
                    className="ml-2 hidden max-w-[12rem] rounded-md border border-border-default bg-surface-2 px-2 py-1 text-sm text-content-primary focus:outline-accent sm:block"
                    aria-label="Active organization"
                >
                    {organizations.map((o) => (
                        <option key={o.id} value={o.id}>
                            {o.name}
                        </option>
                    ))}
                </select>
            )}

            <div className="flex-1" />

            <button
                onClick={() => setCommandPaletteOpen(true)}
                className="hidden items-center gap-2 rounded-md border border-border-default bg-surface-2 px-2.5 py-1.5 text-xs text-content-muted hover:text-content-secondary sm:flex"
            >
                <SearchIcon className="size-4" />
                Jump to…
                <kbd className="rounded bg-surface-3 px-1.5 py-0.5 text-[10px] text-content-secondary">⌘K</kbd>
            </button>

            <span className="flex items-center gap-1.5 text-xs text-content-muted" title={`Realtime: ${connection}`}>
                <span className={cn('size-2 rounded-full', connTone)} />
                <span className="hidden md:inline capitalize">{connection}</span>
            </span>

            <div className="flex items-center gap-2">
                <span className="hidden text-sm text-content-secondary md:inline">{user?.name}</span>
                <Button variant="ghost" size="sm" onClick={signOut}>
                    Sign out
                </Button>
            </div>
        </header>
    );
}
