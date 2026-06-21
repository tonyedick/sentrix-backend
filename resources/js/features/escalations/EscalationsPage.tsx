import { useNavigate, useParams } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { useIncidents } from '@/features/incidents/api';
import { usePrivateChannel } from '@/realtime/useRealtime';
import { Card } from '@/components/ui/Card';
import { DataTable } from '@/components/ui/DataTable';
import type { Column } from '@/components/ui/DataTable';
import { IncidentStatusPill } from '@/components/ui/StatusPill';
import { SeverityChip } from '@/components/ui/SeverityChip';
import { ElapsedTimer } from '@/components/ui/ElapsedTimer';
import { EmptyState } from '@/components/ui/EmptyState';
import type { Incident } from '@/types/api';

/**
 * Escalations lens — a focused view of incidents the escalation engine has
 * raised to `escalated`. Reuses the incidents API (no separate escalation read
 * API exists); a dedicated escalation/audit feed can replace this later.
 */
export function EscalationsPage() {
    const { org = '' } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const { data, isLoading } = useIncidents(org, { status: 'escalated' });

    usePrivateChannel(`organizations.${org}.incidents`, {
        'incident.escalated': () => queryClient.invalidateQueries({ queryKey: ['incidents', org] }),
        'incident.status_changed': () => queryClient.invalidateQueries({ queryKey: ['incidents', org] }),
        'incident.resolved': () => queryClient.invalidateQueries({ queryKey: ['incidents', org] }),
    });

    const rows = data?.data ?? [];

    const columns: Column<Incident>[] = [
        { key: 'title', header: 'Incident', className: 'text-content-primary font-medium', render: (i) => i.title },
        { key: 'severity', header: 'Severity', render: (i) => <SeverityChip severity={i.severity} /> },
        { key: 'status', header: 'Status', render: (i) => <IncidentStatusPill status={i.status} /> },
        { key: 'escalated', header: 'Escalated', render: (i) => <ElapsedTimer since={i.escalated_at ?? i.opened_at} /> },
    ];

    return (
        <div className="mx-auto max-w-6xl space-y-4 p-4 lg:p-6">
            <div className="flex flex-wrap items-center gap-3">
                <h1 className="text-lg font-semibold text-content-primary">Escalations</h1>
                {rows.length > 0 && (
                    <span className="inline-flex items-center gap-1 rounded-full bg-status-danger/15 px-2 py-0.5 text-xs font-medium text-status-danger ring-1 ring-inset ring-status-danger/30">
                        <span className="size-1.5 animate-pulse rounded-full bg-status-danger" />
                        {rows.length} escalated
                    </span>
                )}
            </div>

            <Card>
                <DataTable
                    columns={columns}
                    rows={rows}
                    rowKey={(i) => i.id}
                    isLoading={isLoading}
                    onRowClick={(i) => navigate(`/${org}/incidents/${i.id}`)}
                    rowClassName={() => 'bg-status-danger/[0.05] shadow-[inset_3px_0_0_0_var(--color-status-danger)]'}
                    emptyTitle="No active escalations"
                    emptyHint="Incidents raised by the escalation engine appear here."
                />
            </Card>

            {rows.length === 0 && !isLoading && (
                <EmptyState title="All clear" hint="Nothing is currently escalated." />
            )}
        </div>
    );
}
