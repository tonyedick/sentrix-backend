import { useEffect, useState } from 'react';
import { cn } from '@/lib/cn';

function format(seconds: number): string {
    if (seconds < 0) seconds = 0;
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    if (h > 0) return `${h}h ${String(m).padStart(2, '0')}m`;
    if (m > 0) return `${m}:${String(s).padStart(2, '0')}`;
    return `${s}s`;
}

/**
 * Live-ticking elapsed time since `since`. If `deadline` is provided, renders a
 * countdown and goes danger-toned when it passes. Tabular mono so it never
 * shifts width as it ticks.
 */
export function ElapsedTimer({
    since,
    deadline,
    className,
}: {
    since?: string | null;
    deadline?: string | null;
    className?: string;
}) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const id = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(id);
    }, []);

    if (deadline) {
        const remaining = (new Date(deadline).getTime() - now) / 1000;
        const overdue = remaining <= 0;
        return (
            <span className={cn('tabular text-xs', overdue ? 'text-status-danger' : 'text-status-warn', className)}>
                {overdue ? `-${format(-remaining)}` : format(remaining)}
            </span>
        );
    }

    if (!since) return <span className={cn('tabular text-xs text-content-muted', className)}>—</span>;
    const elapsed = (now - new Date(since).getTime()) / 1000;
    return <span className={cn('tabular text-xs text-content-secondary', className)}>{format(elapsed)}</span>;
}
