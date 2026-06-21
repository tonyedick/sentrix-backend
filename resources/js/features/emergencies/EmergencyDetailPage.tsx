import { Link, useParams } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { useEmergency, useEmergencyAction, emergencyKeys } from '@/features/emergencies/api';
import { usePrivateChannel } from '@/realtime/useRealtime';
import { PermissionGate } from '@/auth/PermissionGate';
import { Card, CardHeader } from '@/components/ui/Card';
import { EmergencyStatusPill } from '@/components/ui/StatusPill';
import { SeverityChip } from '@/components/ui/SeverityChip';
import { ElapsedTimer } from '@/components/ui/ElapsedTimer';
import { Button } from '@/components/ui/Button';
import { SkeletonRows } from '@/components/ui/Skeleton';
import { EmptyState } from '@/components/ui/EmptyState';
import { toast } from '@/components/ui/Toast';
import { ApiError } from '@/lib/api';

export function EmergencyDetailPage() {
    const { org = '', emergencyId = '' } = useParams();
    const queryClient = useQueryClient();
    const { data, isLoading, isError } = useEmergency(org, emergencyId);
    const action = useEmergencyAction(org, emergencyId);

    const invalidate = () => queryClient.invalidateQueries({ queryKey: emergencyKeys.detail(org, emergencyId) });
    usePrivateChannel(`organizations.${org}`, {
        'emergency.acknowledged': invalidate,
        'emergency.resolved': invalidate,
        'emergency.cancelled': invalidate,
    });

    if (isLoading) return <div className="p-6"><SkeletonRows rows={6} /></div>;
    if (isError || !data) {
        return <div className="p-6"><EmptyState title="Emergency not available" /></div>;
    }

    async function run(act: 'acknowledge' | 'resolve' | 'cancel') {
        if (act === 'cancel' && !window.confirm('Cancel this emergency (false alarm)?')) return;
        try {
            await action.mutateAsync({ action: act });
            toast.success(`Emergency ${act}d`);
        } catch (err) {
            toast.error(err instanceof ApiError ? err.message : 'Action failed');
        }
    }

    const open = data.status === 'triggered' || data.status === 'acknowledged';

    return (
        <div className="mx-auto max-w-4xl space-y-4 p-4 lg:p-6">
            <Link to={`/${org}/emergencies`} className="text-xs text-content-muted hover:text-content-secondary">
                ← Back to emergencies
            </Link>

            {data.status === 'triggered' && (
                <div className="flex items-center gap-2 rounded-lg border border-status-danger/40 bg-status-danger/10 px-4 py-2 text-sm font-medium text-status-danger animate-pulse-soft">
                    <span className="size-2 animate-pulse rounded-full bg-status-danger" />
                    Active SOS — awaiting acknowledgement.
                </div>
            )}

            <Card className="p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-2">
                        <h1 className="text-xl font-semibold text-content-primary">{data.message ?? 'SOS'}</h1>
                        <div className="flex flex-wrap items-center gap-3">
                            <EmergencyStatusPill status={data.status} />
                            <SeverityChip severity={data.severity} />
                            <span className="text-xs text-content-muted">
                                Raised <ElapsedTimer since={data.triggered_at ?? data.created_at} className="text-content-secondary" />
                            </span>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {data.status === 'triggered' && (
                            <PermissionGate permission="emergencies.acknowledge">
                                <Button variant="primary" loading={action.isPending} onClick={() => run('acknowledge')}>Acknowledge</Button>
                            </PermissionGate>
                        )}
                        {open && (
                            <PermissionGate permission="emergencies.resolve">
                                <Button variant="secondary" loading={action.isPending} onClick={() => run('resolve')}>Resolve</Button>
                            </PermissionGate>
                        )}
                        {open && (
                            <PermissionGate permission="emergencies.acknowledge">
                                <Button variant="ghost" loading={action.isPending} onClick={() => run('cancel')}>Cancel</Button>
                            </PermissionGate>
                        )}
                    </div>
                </div>
            </Card>

            <Card>
                <CardHeader title="Details" />
                <dl className="grid grid-cols-2 gap-x-6 gap-y-3 p-4 text-sm">
                    <Detail label="Reporter" value={data.user_id ? data.user_id.slice(0, 8) : '—'} />
                    <Detail label="Linked trip" value={data.trip_id ? data.trip_id.slice(0, 8) : '—'} />
                    <Detail label="Location" value={data.location.lat !== null ? `${data.location.lat}, ${data.location.lng}` : '—'} />
                    <Detail label="Acknowledged" value={fmt(data.acknowledged_at)} />
                    <Detail label="Resolved" value={fmt(data.resolved_at)} />
                    <Detail label="Cancelled" value={fmt(data.cancelled_at)} />
                </dl>
            </Card>
        </div>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-content-muted">{label}</dt>
            <dd className="mt-0.5 font-mono text-content-secondary">{value}</dd>
        </div>
    );
}

function fmt(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString() : '—';
}
