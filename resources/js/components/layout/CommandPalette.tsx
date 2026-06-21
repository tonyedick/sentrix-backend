import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useUiStore } from '@/state/uiStore';
import { useAuth } from '@/auth/AuthProvider';
import { cn } from '@/lib/cn';

interface Command {
    label: string;
    to: string;
    permission?: string;
}

/** Lightweight ⌘K navigator across the Phase 1 pages. */
export function CommandPalette() {
    const open = useUiStore((s) => s.commandPaletteOpen);
    const setOpen = useUiStore((s) => s.setCommandPaletteOpen);
    const { org } = useParams();
    const { can } = useAuth();
    const navigate = useNavigate();
    const [query, setQuery] = useState('');

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen(true);
            }
            if (e.key === 'Escape') setOpen(false);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [setOpen]);

    useEffect(() => {
        if (open) setQuery('');
    }, [open]);

    if (!open) return null;

    const commands: Command[] = [
        { label: 'Dashboard', to: `/${org}/dashboard`, permission: 'incidents.view' },
        { label: 'Incidents', to: `/${org}/incidents`, permission: 'incidents.view' },
        { label: 'Emergencies', to: `/${org}/emergencies`, permission: 'emergencies.view' },
        { label: 'Escalations', to: `/${org}/escalations`, permission: 'incidents.view' },
        { label: 'Responders', to: `/${org}/responders`, permission: 'responders.view' },
        { label: 'Notifications', to: `/${org}/notifications` },
        { label: 'Organization Settings', to: `/${org}/settings` },
    ].filter((c) => !c.permission || can(c.permission));

    const filtered = commands.filter((c) => c.label.toLowerCase().includes(query.toLowerCase()));

    const go = (to: string) => {
        setOpen(false);
        navigate(to);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center pt-[15vh]">
            <div className="absolute inset-0 bg-black/60" onClick={() => setOpen(false)} />
            <div className="relative w-full max-w-lg overflow-hidden rounded-xl border border-border-default bg-surface-1 shadow-2xl">
                <input
                    autoFocus
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && filtered[0] && go(filtered[0].to)}
                    placeholder="Jump to a page…"
                    className="w-full border-b border-border-subtle bg-transparent px-4 py-3 text-sm text-content-primary placeholder:text-content-muted focus:outline-none"
                />
                <ul className="max-h-72 overflow-y-auto py-1">
                    {filtered.map((c) => (
                        <li key={c.to}>
                            <button
                                onClick={() => go(c.to)}
                                className={cn(
                                    'w-full px-4 py-2 text-left text-sm text-content-secondary hover:bg-surface-2 hover:text-content-primary',
                                )}
                            >
                                {c.label}
                            </button>
                        </li>
                    ))}
                    {filtered.length === 0 && <li className="px-4 py-3 text-sm text-content-muted">No matches</li>}
                </ul>
            </div>
        </div>
    );
}
