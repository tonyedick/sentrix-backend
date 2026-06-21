import { useMemo, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { useIncidents } from '@/features/incidents/api';
import { usePrivateChannel } from '@/realtime/useRealtime';
import { PermissionGate } from '@/auth/PermissionGate';
import { DataTable } from '@/components/ui/DataTable';
import type { Column } from '@/components/ui/DataTable';
import { IncidentStatusPill } from '@/components/ui/StatusPill';
import { SeverityChip } from '@/components/ui/SeverityChip';
import { ElapsedTimer } from '@/components/ui/ElapsedTimer';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { SearchInput } from '@/components/ui/SearchInput';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { NewIncidentSlideOver } from '@/features/incidents/components/NewIncidentSlideOver';
import type { Incident, IncidentSeverity } from '@/types/api';

const STATUS_SEGMENTS = [
    { value: '', label: 'All' },
    { value: 'open', label: 'Open' },
    { value: 'investigating', label: 'Investigating' },
    { value: 'escalated', label: 'Escalated' },
    { value: 'resolved', label: 'Resolved' },
    { value: 'closed', label: 'Closed' },
];
const SEVERITIES: IncidentSeverity[] = ['low', 'medium', 'high', 'critical'];

// Attention ordering: escalated first, then by severity, then newest.
const severityRank: Record<IncidentSeverity, number> = { low: 1, medium: 2, high: 3, critical: 4 };
function attentionScore(i: Incident): number {
    const escalated = i.status === 'escalated' ? 100 : 0;
    return escalated + severityRank[i.severity];
}

export function IncidentListPage() {
    const { org = '' } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [params, setParams] = useSearchParams();
    const [creating, setCreating] = useState(false);

    const filters = {
        status: params.get('status') ?? undefined,
        severity: params.get('severity') ?? undefined,
        page: params.get('page') ? Number(params.get('page')) : undefined,
    };
    const search = params.get('q') ?? '';

    const { data, isLoading } = useIncidents(org, filters);

    usePrivateChannel(`organizations.${org}.incidents`, {
        'incident.opened': () => queryClient.invalidateQueries({ queryKey: ['incidents', org] }),
        'incident.status_changed': () => queryClient.invalidateQueries({ queryKey: ['incidents', org] }),
        'incident.escalated': () => queryClient.invalidateQueries({ queryKey: ['incidents', org] }),
        'incident.resolved': () => queryClient.invalidateQueries({ queryKey: ['incidents', org] }),
        'incident.closed': () => queryClient.invalidateQueries({ queryKey: ['incidents', org] }),
    });

    function setParam(key: string, value: string) {
        const next = new URLSearchParams(params);
        if (value) next.set(key, value);
        else next.delete(key);
        if (key !== 'page') next.delete('page');
        setParams(next, { replace: true });
    }

    // Search is client-side over the loaded page (the index API has no text
    // search); status/severity are server-side filters.
    const rows = useMemo(() => {
        const all = data?.data ?? [];
        const q = search.trim().toLowerCase();
        const filtered = q
            ? all.filter(
                  (i) =>
                      i.title.toLowerCase().includes(q) ||
                      (i.summary ?? '').toLowerCase().includes(q),
              )
            : all;
        return [...filtered].sort((a, b) => attentionScore(b) - attentionScore(a));
    }, [data?.data, search]);

    const escalatedCount = rows.filter((i) => i.status === 'escalated').length;

    const columns: Column<Incident>[] = [
        {
            key: 'title',
            header: 'Incident',
            className: 'text-content-primary font-medium',
            render: (i) => (
                <div className="flex flex-col">
                    <span>{i.title}</span>
                    {i.summary && <span className="truncate text-xs text-content-muted">{i.summary}</span>}
                </div>
            ),
        },
        { key: 'severity', header: 'Severity', render: (i) => <SeverityChip severity={i.severity} /> },
        { key: 'status', header: 'Status', render: (i) => <IncidentStatusPill status={i.status} /> },
        { key: 'elapsed', header: 'Open for', render: (i) => <ElapsedTimer since={i.opened_at ?? i.created_at} /> },
    ];

    const rowClassName = (i: Incident): string | undefined => {
        if (i.status === 'escalated')
            return 'bg-status-danger/[0.06] shadow-[inset_3px_0_0_0_var(--color-status-danger)] animate-pulse-soft';
        if (i.severity === 'critical')
            return 'shadow-[inset_3px_0_0_0_var(--color-severity-critical)]';
        if (i.status === 'resolved' || i.status === 'closed') return 'opacity-55';
        return undefined;
    };

    const meta = data?.meta;

    return (
        <div className="mx-auto max-w-6xl space-y-4 p-4 lg:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <h1 className="text-lg font-semibold text-content-primary">Incidents</h1>
                    {escalatedCount > 0 && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-status-danger/15 px-2 py-0.5 text-xs font-medium text-status-danger ring-1 ring-inset ring-status-danger/30">
                            <span className="size-1.5 animate-pulse rounded-full bg-status-danger" />
                            {escalatedCount} escalated
                        </span>
                    )}
                </div>
                <PermissionGate permission="incidents.create">
                    <Button variant="primary" onClick={() => setCreating(true)}>
                        Open incident
                    </Button>
                </PermissionGate>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <SegmentedControl segments={STATUS_SEGMENTS} value={filters.status ?? ''} onChange={(v) => setParam('status', v)} />
                <select
                    value={filters.severity ?? ''}
                    onChange={(e) => setParam('severity', e.target.value)}
                    className="rounded-md border border-border-default bg-surface-2 px-2.5 py-1.5 text-sm capitalize text-content-secondary focus:outline-accent"
                >
                    <option value="">All severities</option>
                    {SEVERITIES.map((s) => (
                        <option key={s} value={s}>
                            {s}
                        </option>
                    ))}
                </select>
                <div className="min-w-[12rem] flex-1">
                    <SearchInput value={search} onChange={(v) => setParam('q', v)} placeholder="Search incidents…" />
                </div>
            </div>

            <Card>
                <DataTable
                    columns={columns}
                    rows={rows}
                    rowKey={(i) => i.id}
                    isLoading={isLoading}
                    onRowClick={(i) => navigate(`/${org}/incidents/${i.id}`)}
                    rowClassName={rowClassName}
                    emptyTitle={search ? 'No incidents match your search' : 'No incidents match these filters'}
                    emptyHint={search ? 'Search covers the loaded page; clear it to see more.' : 'Clear filters or open a new incident.'}
                />
                {meta && meta.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border-subtle px-4 py-2 text-xs text-content-muted">
                        <span>
                            Page {meta.current_page} of {meta.last_page} · {meta.total} total
                        </span>
                        <div className="flex gap-2">
                            <Button size="sm" disabled={meta.current_page <= 1} onClick={() => setParam('page', String(meta.current_page - 1))}>
                                Prev
                            </Button>
                            <Button size="sm" disabled={meta.current_page >= meta.last_page} onClick={() => setParam('page', String(meta.current_page + 1))}>
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </Card>

            <NewIncidentSlideOver org={org} open={creating} onClose={() => setCreating(false)} />
        </div>
    );
}
