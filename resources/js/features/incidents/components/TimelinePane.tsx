import { Card, CardHeader } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import type { TimelineEntry } from '@/types/api';

const sourceTone: Record<string, string> = {
    incident: 'bg-status-info',
    assignment: 'bg-accent',
    responder: 'bg-status-success',
    escalation: 'bg-status-danger',
    system: 'bg-content-muted',
};

export function TimelinePane({ timeline }: { timeline: TimelineEntry[] }) {
    const entries = [...timeline].reverse(); // newest first for the operator

    return (
        <Card>
            <CardHeader
                title={`Timeline${timeline.length ? ` · ${timeline.length}` : ''}`}
                action={<span className="text-[11px] uppercase tracking-wide text-content-muted">Persisted record</span>}
            />
            {entries.length === 0 ? (
                <div className="p-4">
                    <EmptyState title="No timeline activity yet" />
                </div>
            ) : (
                <ol className="relative space-y-4 p-4 pl-6">
                    <span className="absolute bottom-2 left-2.5 top-2 w-px bg-border-subtle" aria-hidden="true" />
                    {entries.map((entry, idx) => (
                        <li key={`${entry.type}-${entry.at}-${idx}`} className="relative">
                            <span
                                className={`absolute -left-[15px] top-1 size-2.5 rounded-full ring-4 ring-surface-1 ${
                                    sourceTone[entry.source] ?? 'bg-content-muted'
                                }`}
                            />
                            <div className="flex items-baseline justify-between gap-2">
                                <p className="text-sm font-medium capitalize text-content-primary">
                                    {entry.type.replace(/[._]/g, ' ')}
                                </p>
                                <time className="tabular shrink-0 text-[11px] text-content-muted">
                                    {entry.at ? new Date(entry.at).toLocaleTimeString() : ''}
                                </time>
                            </div>
                            <p className="text-[11px] uppercase tracking-wide text-content-muted">{entry.source}</p>
                        </li>
                    ))}
                </ol>
            )}
        </Card>
    );
}
