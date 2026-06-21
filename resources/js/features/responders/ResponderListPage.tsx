import { useMemo } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useResponders } from '@/features/responders/api';
import { useFleetPresence } from '@/features/responders/useFleetPresence';
import { Card } from '@/components/ui/Card';
import { DataTable } from '@/components/ui/DataTable';
import type { Column } from '@/components/ui/DataTable';
import { ResponderStatusPill } from '@/components/ui/StatusPill';
import { PresenceDot } from '@/components/ui/PresenceDot';
import { Freshness, isStale } from '@/components/ui/Freshness';
import { SearchInput } from '@/components/ui/SearchInput';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import type { Responder, ResponderStatus } from '@/types/api';

const STATUS_SEGMENTS = [
    { value: '', label: 'All' },
    { value: 'available', label: 'Available' },
    { value: 'on_assignment', label: 'On assignment' },
    { value: 'off_duty', label: 'Off duty' },
    { value: 'unavailable', label: 'Unavailable' },
    { value: 'suspended', label: 'Suspended' },
];

const ENGAGED: ResponderStatus[] = ['on_assignment', 'en_route', 'on_scene'];

function engagementLabel(status: ResponderStatus): string {
    if (ENGAGED.includes(status)) return `Engaged · ${status.replace(/_/g, ' ')}`;
    if (status === 'available') return 'Idle · ready';
    return '—';
}

export function ResponderListPage() {
    const { org = '' } = useParams();
    const navigate = useNavigate();
    const [params, setParams] = useSearchParams();

    const filters = {
        status: params.get('status') ?? undefined,
        assignable: params.get('assignable') === '1' ? true : undefined,
        page: params.get('page') ? Number(params.get('page')) : undefined,
    };
    const search = params.get('q') ?? '';
    const onlineOnly = params.get('online') === '1';

    const { data, isLoading } = useResponders(org, filters);
    const { online } = useFleetPresence(org);

    function setParam(key: string, value: string) {
        const next = new URLSearchParams(params);
        if (value) next.set(key, value);
        else next.delete(key);
        if (key !== 'page') next.delete('page');
        setParams(next, { replace: true });
    }

    const all = data?.data ?? [];
    const rows = useMemo(() => {
        const q = search.trim().toLowerCase();
        return all.filter((r) => {
            if (onlineOnly && !online.has(r.user_id)) return false;
            if (q && !r.user_id.toLowerCase().includes(q) && !r.id.toLowerCase().includes(q)) return false;
            return true;
        });
    }, [all, search, onlineOnly, online]);

    const summary = {
        online: online.size,
        available: all.filter((r) => r.status === 'available').length,
        engaged: all.filter((r) => ENGAGED.includes(r.status)).length,
        offDuty: all.filter((r) => r.status === 'off_duty' || !r.on_duty).length,
    };

    const columns: Column<Responder>[] = [
        {
            key: 'responder',
            header: 'Responder',
            className: 'text-content-primary',
            render: (r) => (
                <div className="flex items-center gap-2">
                    <PresenceDot tone={online.has(r.user_id) ? 'success' : 'neutral'} pulse={online.has(r.user_id)} />
                    <span className="font-mono text-xs">{r.user_id.slice(0, 8)}</span>
                </div>
            ),
        },
        { key: 'status', header: 'Status', render: (r) => <ResponderStatusPill status={r.status} /> },
        {
            key: 'duty',
            header: 'Duty',
            render: (r) => <span className="text-xs text-content-secondary">{r.on_duty ? 'On duty' : 'Off duty'}</span>,
        },
        {
            key: 'assignable',
            header: 'Assignable',
            render: (r) =>
                r.assignable ? <span className="text-xs text-status-success">Yes</span> : <span className="text-xs text-content-muted">No</span>,
        },
        {
            key: 'connectivity',
            header: 'Connectivity',
            render: (r) => (
                <div className="flex items-center gap-1.5">
                    <span
                        className={`size-1.5 rounded-full ${online.has(r.user_id) ? 'bg-status-success' : isStale(r.last_seen_at) ? 'bg-status-warn' : 'bg-content-muted'}`}
                    />
                    <Freshness at={r.last_seen_at} />
                </div>
            ),
        },
        { key: 'engagement', header: 'Engagement', render: (r) => <span className="text-xs text-content-secondary">{engagementLabel(r.status)}</span> },
    ];

    const rowClassName = (r: Responder): string | undefined => {
        if (r.status === 'suspended') return 'bg-status-danger/[0.05] shadow-[inset_3px_0_0_0_var(--color-status-danger)]';
        if (!online.has(r.user_id) && isStale(r.last_seen_at) && r.on_duty)
            return 'shadow-[inset_3px_0_0_0_var(--color-status-warn)]'; // on duty but disconnected — worth noticing
        if (r.status === 'off_duty') return 'opacity-55';
        return undefined;
    };

    const meta = data?.meta;

    return (
        <div className="mx-auto max-w-6xl space-y-4 p-4 lg:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-lg font-semibold text-content-primary">Responders</h1>
                <div className="flex flex-wrap items-center gap-3 text-xs">
                    <Stat label="online" value={summary.online} tone="text-status-success" />
                    <Stat label="available" value={summary.available} tone="text-status-success" />
                    <Stat label="engaged" value={summary.engaged} tone="text-status-info" />
                    <Stat label="off duty" value={summary.offDuty} tone="text-content-muted" />
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <SegmentedControl segments={STATUS_SEGMENTS} value={filters.status ?? ''} onChange={(v) => setParam('status', v)} />
                <label className="flex items-center gap-1.5 text-xs text-content-secondary">
                    <input
                        type="checkbox"
                        checked={filters.assignable === true}
                        onChange={(e) => setParam('assignable', e.target.checked ? '1' : '')}
                        className="accent-[var(--color-accent)]"
                    />
                    Assignable only
                </label>
                <label className="flex items-center gap-1.5 text-xs text-content-secondary">
                    <input
                        type="checkbox"
                        checked={onlineOnly}
                        onChange={(e) => setParam('online', e.target.checked ? '1' : '')}
                        className="accent-[var(--color-accent)]"
                    />
                    Online only
                </label>
                <div className="min-w-[12rem] flex-1">
                    <SearchInput value={search} onChange={(v) => setParam('q', v)} placeholder="Search by id…" />
                </div>
            </div>

            <Card>
                <DataTable
                    columns={columns}
                    rows={rows}
                    rowKey={(r) => r.id}
                    isLoading={isLoading}
                    onRowClick={(r) => navigate(`/${org}/responders/${r.id}`)}
                    rowClassName={rowClassName}
                    emptyTitle="No responders match"
                    emptyHint={search || onlineOnly ? 'Filters apply to the loaded page.' : undefined}
                />
                {meta && meta.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border-subtle px-4 py-2 text-xs text-content-muted">
                        <span>
                            Page {meta.current_page} of {meta.last_page} · {meta.total} total
                        </span>
                        <div className="flex gap-2">
                            <button
                                disabled={meta.current_page <= 1}
                                onClick={() => setParam('page', String(meta.current_page - 1))}
                                className="rounded border border-border-default px-2 py-1 disabled:opacity-40"
                            >
                                Prev
                            </button>
                            <button
                                disabled={meta.current_page >= meta.last_page}
                                onClick={() => setParam('page', String(meta.current_page + 1))}
                                className="rounded border border-border-default px-2 py-1 disabled:opacity-40"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                )}
            </Card>
        </div>
    );
}

function Stat({ label, value, tone }: { label: string; value: number; tone: string }) {
    return (
        <span className="inline-flex items-center gap-1">
            <span className={`tabular font-semibold ${tone}`}>{value}</span>
            <span className="text-content-muted">{label}</span>
        </span>
    );
}
