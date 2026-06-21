import { Link, useParams } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { useIncidents } from '@/features/incidents/api';
import { useResponders } from '@/features/responders/api';
import { usePrivateChannel } from '@/realtime/useRealtime';
import { Card, CardHeader } from '@/components/ui/Card';
import { IncidentStatusPill } from '@/components/ui/StatusPill';
import { SeverityChip } from '@/components/ui/SeverityChip';
import { ElapsedTimer } from '@/components/ui/ElapsedTimer';
import { EmptyState } from '@/components/ui/EmptyState';
import { SkeletonRows } from '@/components/ui/Skeleton';
import type { Incident } from '@/types/api';

const ACTIVE_STATUSES = ['open', 'investigating', 'escalated'];

export function DashboardPage() {
    const { org = '' } = useParams();
    const queryClient = useQueryClient();

    const incidents = useIncidents(org, {});
    const responders = useResponders(org, {});

    // The dashboard channel carries incident, assignment and responder events.
    usePrivateChannel(`organizations.${org}.dashboard`, {
        'incident.opened': () => queryClient.invalidateQueries({ queryKey: ['incidents', org] }),
        'incident.status_changed': () => queryClient.invalidateQueries({ queryKey: ['incidents', org] }),
        'incident.escalated': () => queryClient.invalidateQueries({ queryKey: ['incidents', org] }),
        'incident.resolved': () => queryClient.invalidateQueries({ queryKey: ['incidents', org] }),
        'incident.closed': () => queryClient.invalidateQueries({ queryKey: ['incidents', org] }),
        'responder.status_changed': () => queryClient.invalidateQueries({ queryKey: ['responders', org] }),
    });

    const allIncidents = incidents.data?.data ?? [];
    const active = allIncidents.filter((i) => ACTIVE_STATUSES.includes(i.status));
    const escalated = active.filter((i) => i.status === 'escalated');

    const roster = responders.data?.data ?? [];
    const available = roster.filter((r) => r.status === 'available').length;
    const onDuty = roster.filter((r) => r.on_duty).length;

    return (
        <div className="mx-auto max-w-6xl space-y-4 p-4 lg:p-6">
            <h1 className="text-lg font-semibold text-content-primary">Operations Overview</h1>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Stat label="Active incidents" value={active.length} tone="info" />
                <Stat label="Escalated" value={escalated.length} tone="danger" />
                <Stat label="Available responders" value={available} tone="success" />
                <Stat label="On duty" value={onDuty} tone="neutral" />
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader
                        title={`Active incidents${active.length ? ` · ${active.length}` : ''}`}
                        action={
                            <Link to={`/${org}/incidents`} className="text-xs text-accent hover:text-accent-hover">
                                View all
                            </Link>
                        }
                    />
                    <div className="p-4">
                        {incidents.isLoading ? (
                            <SkeletonRows rows={4} />
                        ) : active.length === 0 ? (
                            <EmptyState title="No active incidents" hint="All clear right now." />
                        ) : (
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {active.map((i) => (
                                    <IncidentCard key={i.id} org={org} incident={i} />
                                ))}
                            </div>
                        )}
                    </div>
                </Card>

                <Card>
                    <CardHeader title="Responder availability" />
                    <div className="p-4">
                        {responders.isLoading ? (
                            <SkeletonRows rows={3} />
                        ) : (
                            <ul className="space-y-2 text-sm">
                                <Availability label="Available" value={available} tone="text-status-success" />
                                <Availability label="On duty" value={onDuty} tone="text-content-secondary" />
                                <Availability label="Total roster" value={roster.length} tone="text-content-secondary" />
                            </ul>
                        )}
                        <Link
                            to={`/${org}/responders`}
                            className="mt-4 inline-block text-xs text-accent hover:text-accent-hover"
                        >
                            Manage responders →
                        </Link>
                    </div>
                </Card>
            </div>
        </div>
    );
}

function Stat({ label, value, tone }: { label: string; value: number; tone: 'info' | 'danger' | 'success' | 'neutral' }) {
    const toneClass = {
        info: 'text-status-info',
        danger: 'text-status-danger',
        success: 'text-status-success',
        neutral: 'text-content-primary',
    }[tone];
    return (
        <Card className="p-4">
            <p className="text-xs uppercase tracking-wide text-content-muted">{label}</p>
            <p className={`mt-1 tabular text-2xl font-semibold ${toneClass}`}>{value}</p>
        </Card>
    );
}

function Availability({ label, value, tone }: { label: string; value: number; tone: string }) {
    return (
        <li className="flex items-center justify-between">
            <span className="text-content-muted">{label}</span>
            <span className={`tabular font-medium ${tone}`}>{value}</span>
        </li>
    );
}

function IncidentCard({ org, incident }: { org: string; incident: Incident }) {
    return (
        <Link
            to={`/${org}/incidents/${incident.id}`}
            className="block rounded-lg border border-border-subtle bg-surface-2 p-3 transition-colors hover:border-border-strong hover:bg-surface-3"
        >
            <div className="flex items-start justify-between gap-2">
                <p className="text-sm font-medium text-content-primary">{incident.title}</p>
                <IncidentStatusPill status={incident.status} />
            </div>
            <div className="mt-2 flex items-center justify-between">
                <SeverityChip severity={incident.severity} />
                <ElapsedTimer since={incident.opened_at ?? incident.created_at} />
            </div>
        </Link>
    );
}
