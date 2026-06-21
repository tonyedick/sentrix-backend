import type { ReactNode } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { useIncident } from '@/features/incidents/api';
import { useIncidentRealtime } from '@/features/incidents/useIncidentRealtime';
import { IncidentHeader } from '@/features/incidents/components/IncidentHeader';
import { TimelinePane } from '@/features/incidents/components/TimelinePane';
import { ActivityFeed } from '@/features/incidents/components/ActivityFeed';
import { AssignmentPanel } from '@/features/incidents/components/AssignmentPanel';
import { Card, CardHeader } from '@/components/ui/Card';
import { SkeletonRows } from '@/components/ui/Skeleton';
import { EmptyState } from '@/components/ui/EmptyState';
import { cn } from '@/lib/cn';

type WorkspaceTab = 'timeline' | 'activity';

export function IncidentDetailPage() {
    const { org = '', incidentId = '' } = useParams();
    const [params, setParams] = useSearchParams();
    const tab = (params.get('tab') as WorkspaceTab) || 'timeline';

    // One subscription drives both cache invalidation and the live activity feed.
    const activity = useIncidentRealtime(org, incidentId);
    const { data, isLoading, isError } = useIncident(org, incidentId);

    function setTab(next: WorkspaceTab) {
        const p = new URLSearchParams(params);
        p.set('tab', next);
        setParams(p, { replace: true });
    }

    if (isLoading) return <div className="p-6"><SkeletonRows rows={8} /></div>;
    if (isError || !data) {
        return (
            <div className="p-6">
                <EmptyState title="Incident not available" hint="It may have been closed or you may not have access." />
            </div>
        );
    }

    const escalated = data.incident.status === 'escalated';

    return (
        <div className="mx-auto max-w-7xl space-y-4 p-4 lg:p-6">
            <Link to={`/${org}/incidents`} className="text-xs text-content-muted hover:text-content-secondary">
                ← Back to incidents
            </Link>

            {escalated && (
                <div className="flex items-center gap-2 rounded-lg border border-status-danger/40 bg-status-danger/10 px-4 py-2 text-sm font-medium text-status-danger animate-pulse-soft">
                    <span className="size-2 animate-pulse rounded-full bg-status-danger" />
                    This incident is escalated — immediate attention required.
                </div>
            )}

            <IncidentHeader org={org} incident={data.incident} />

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                {/* Main column: details + tabbed Timeline / Activity */}
                <div className="space-y-4 lg:col-span-2">
                    <Card>
                        <CardHeader title="Details" />
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 p-4 text-sm">
                            <Detail label="Summary" value={data.incident.summary ?? '—'} span />
                            <Detail label="Opened" value={fmt(data.incident.opened_at ?? data.incident.created_at)} />
                            <Detail label="Escalated" value={fmt(data.incident.escalated_at)} />
                            <Detail label="Resolved" value={fmt(data.incident.resolved_at)} />
                            <Detail label="Closed" value={fmt(data.incident.closed_at)} />
                        </dl>
                    </Card>

                    <Card className="overflow-hidden">
                        <div className="flex border-b border-border-subtle">
                            <TabButton active={tab === 'timeline'} onClick={() => setTab('timeline')}>
                                Timeline
                            </TabButton>
                            <TabButton active={tab === 'activity'} onClick={() => setTab('activity')}>
                                Live activity
                                {activity.length > 0 && (
                                    <span className="ml-1.5 rounded-full bg-accent/20 px-1.5 text-[10px] text-accent">{activity.length}</span>
                                )}
                            </TabButton>
                        </div>
                        {tab === 'timeline' ? <TimelinePane timeline={data.timeline} /> : <ActivityFeed items={activity} />}
                    </Card>
                </div>

                {/* Dispatch rail */}
                <div className="lg:col-span-1">
                    <AssignmentPanel
                        org={org}
                        incidentId={data.incident.id}
                        assignment={data.assignment}
                        incidentStatus={data.incident.status}
                    />
                </div>
            </div>
        </div>
    );
}

function TabButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: ReactNode }) {
    return (
        <button
            onClick={onClick}
            className={cn(
                'inline-flex items-center border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                active ? 'border-accent text-content-primary' : 'border-transparent text-content-muted hover:text-content-secondary',
            )}
        >
            {children}
        </button>
    );
}

function Detail({ label, value, span = false }: { label: string; value: string; span?: boolean }) {
    return (
        <div className={span ? 'col-span-2' : ''}>
            <dt className="text-xs uppercase tracking-wide text-content-muted">{label}</dt>
            <dd className="mt-0.5 text-content-secondary">{value}</dd>
        </div>
    );
}

function fmt(iso: string | null | undefined): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}
