import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { useEmergencies } from '@/features/emergencies/api';
import { usePrivateChannel } from '@/realtime/useRealtime';
import { Card } from '@/components/ui/Card';
import { DataTable } from '@/components/ui/DataTable';
import type { Column } from '@/components/ui/DataTable';
import { EmergencyStatusPill } from '@/components/ui/StatusPill';
import { SeverityChip } from '@/components/ui/SeverityChip';
import { ElapsedTimer } from '@/components/ui/ElapsedTimer';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import type { Emergency } from '@/types/api';

const STATUS_SEGMENTS = [
    { value: '', label: 'All' },
    { value: 'triggered', label: 'Triggered' },
    { value: 'acknowledged', label: 'Acknowledged' },
    { value: 'resolved', label: 'Resolved' },
    { value: 'cancelled', label: 'Cancelled' },
];

export function EmergencyListPage() {
    const { org = '' } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [params, setParams] = useSearchParams();

    const filters = {
        status: params.get('status') ?? undefined,
        page: params.get('page') ? Number(params.get('page')) : undefined,
    };

    const { data, isLoading } = useEmergencies(org, filters);

    // Emergency events route to the general org channel (broadcastOn default).
    const invalidate = () => queryClient.invalidateQueries({ queryKey: ['emergencies', org] });
    usePrivateChannel(`organizations.${org}`, {
        'emergency.triggered': invalidate,
        'emergency.acknowledged': invalidate,
        'emergency.resolved': invalidate,
        'emergency.cancelled': invalidate,
    });

    function setStatus(value: string) {
        const next = new URLSearchParams(params);
        if (value) next.set('status', value);
        else next.delete('status');
        next.delete('page');
        setParams(next, { replace: true });
    }

    const rows = data?.data ?? [];
    const active = rows.filter((e) => e.status === 'triggered').length;

    const columns: Column<Emergency>[] = [
        {
            key: 'message',
            header: 'Emergency',
            className: 'text-content-primary font-medium',
            render: (e) => (
                <div className="flex flex-col">
                    <span>{e.message ?? 'SOS'}</span>
                    <span className="font-mono text-xs text-content-muted">{e.user_id ? e.user_id.slice(0, 8) : '—'}</span>
                </div>
            ),
        },
        { key: 'severity', header: 'Severity', render: (e) => <SeverityChip severity={e.severity} /> },
        { key: 'status', header: 'Status', render: (e) => <EmergencyStatusPill status={e.status} /> },
        { key: 'elapsed', header: 'Raised', render: (e) => <ElapsedTimer since={e.triggered_at ?? e.created_at} /> },
    ];

    const rowClassName = (e: Emergency): string | undefined => {
        if (e.status === 'triggered') return 'bg-status-danger/[0.07] shadow-[inset_3px_0_0_0_var(--color-status-danger)] animate-pulse-soft';
        if (e.status === 'acknowledged') return 'shadow-[inset_3px_0_0_0_var(--color-status-warn)]';
        if (e.status === 'resolved' || e.status === 'cancelled') return 'opacity-55';
        return undefined;
    };

    const meta = data?.meta;

    return (
        <div className="mx-auto max-w-6xl space-y-4 p-4 lg:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <h1 className="text-lg font-semibold text-content-primary">Emergencies</h1>
                    {active > 0 && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-status-danger/15 px-2 py-0.5 text-xs font-medium text-status-danger ring-1 ring-inset ring-status-danger/30">
                            <span className="size-1.5 animate-pulse rounded-full bg-status-danger" />
                            {active} active SOS
                        </span>
                    )}
                </div>
            </div>

            <SegmentedControl segments={STATUS_SEGMENTS} value={filters.status ?? ''} onChange={setStatus} />

            <Card>
                <DataTable
                    columns={columns}
                    rows={rows}
                    rowKey={(e) => e.id}
                    isLoading={isLoading}
                    onRowClick={(e) => navigate(`/${org}/emergencies/${e.id}`)}
                    rowClassName={rowClassName}
                    emptyTitle="No emergencies"
                    emptyHint="Active SOS events will appear here in realtime."
                />
                {meta && meta.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border-subtle px-4 py-2 text-xs text-content-muted">
                        <span>Page {meta.current_page} of {meta.last_page} · {meta.total} total</span>
                    </div>
                )}
            </Card>
        </div>
    );
}
