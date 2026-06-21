import { useEcho } from '@/realtime/EchoProvider';
import { EmptyState } from '@/components/ui/EmptyState';
import { cn } from '@/lib/cn';
import type { ActivityItem } from '@/features/incidents/useIncidentRealtime';
import type { Tone } from '@/components/ui/StatusPill';

const dotClass: Record<Tone, string> = {
    info: 'bg-status-info',
    success: 'bg-status-success',
    warn: 'bg-status-warn',
    danger: 'bg-status-danger',
    neutral: 'bg-content-muted',
    accent: 'bg-accent',
};

/**
 * Live, ephemeral event stream for the current incident. Complements the
 * persisted Timeline — items appear the instant Reverb broadcasts, newest first,
 * and flash in. Cleared on reload (the Timeline is the durable record).
 */
export function ActivityFeed({ items }: { items: ActivityItem[] }) {
    const { connection } = useEcho();
    const live = connection === 'connected';

    return (
        <div>
            <div className="flex items-center gap-2 border-b border-border-subtle px-4 py-3">
                <h2 className="text-sm font-semibold text-content-primary">Live activity</h2>
                <span
                    className={cn(
                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide',
                        live ? 'bg-status-success/15 text-status-success' : 'bg-status-warn/15 text-status-warn',
                    )}
                >
                    <span className={cn('size-1.5 rounded-full', live ? 'animate-pulse bg-status-success' : 'bg-status-warn')} />
                    {live ? 'Live' : 'Reconnecting'}
                </span>
            </div>

            {items.length === 0 ? (
                <div className="p-4">
                    <EmptyState title="Waiting for live events" hint="Dispatch and status changes will stream here as they happen." />
                </div>
            ) : (
                <ul className="max-h-[26rem] divide-y divide-border-subtle/60 overflow-y-auto">
                    {items.map((item) => (
                        <li key={item.id} className="flex items-center gap-3 px-4 py-2.5 animate-flash-in">
                            <span className={cn('size-2 shrink-0 rounded-full', dotClass[item.tone])} />
                            <span className="flex-1 text-sm text-content-secondary">{item.label}</span>
                            <time className="tabular shrink-0 text-[11px] text-content-muted">
                                {new Date(item.at).toLocaleTimeString()}
                            </time>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
