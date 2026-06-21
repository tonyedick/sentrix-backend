import { useEffect, useState } from 'react';
import { cn } from '@/lib/cn';

const STALE_AFTER_S = 300; // 5 min without a fix/heartbeat → stale

function relative(seconds: number): string {
    if (seconds < 60) return `${Math.floor(seconds)}s ago`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
}

/**
 * Last-seen freshness indicator. Drives the "offline/stale responders are
 * immediately visible" goal: never-seen → offline, >5m → stale (amber).
 */
export function Freshness({ at, className }: { at: string | null; className?: string }) {
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        const id = window.setInterval(() => setNow(Date.now()), 15_000);
        return () => window.clearInterval(id);
    }, []);

    if (!at) return <span className={cn('text-xs text-content-muted', className)}>never seen</span>;

    const seconds = (now - new Date(at).getTime()) / 1000;
    const stale = seconds > STALE_AFTER_S;
    return (
        <span className={cn('tabular text-xs', stale ? 'text-status-warn' : 'text-content-secondary', className)}>
            {relative(seconds)}
            {stale && ' · stale'}
        </span>
    );
}

export function isStale(at: string | null): boolean {
    if (!at) return true;
    return (Date.now() - new Date(at).getTime()) / 1000 > STALE_AFTER_S;
}
