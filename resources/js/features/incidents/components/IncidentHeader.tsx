import { useIncidentTransition } from '@/features/incidents/api';
import { ApiError } from '@/lib/api';
import { PermissionGate } from '@/auth/PermissionGate';
import { IncidentStatusPill } from '@/components/ui/StatusPill';
import { SeverityChip } from '@/components/ui/SeverityChip';
import { ElapsedTimer } from '@/components/ui/ElapsedTimer';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { toast } from '@/components/ui/Toast';
import type { Incident, IncidentStatus } from '@/types/api';

type Action = 'investigate' | 'escalate' | 'resolve' | 'close';

// Legal next transitions per status (mirrors the backend state machine; the
// server is still the authority and will reject anything illegal).
const transitions: Record<IncidentStatus, { action: Action; label: string; permission: string; danger?: boolean; confirm?: boolean }[]> = {
    open: [
        { action: 'investigate', label: 'Investigate', permission: 'incidents.update' },
        { action: 'escalate', label: 'Escalate', permission: 'incidents.escalate', danger: true },
        { action: 'resolve', label: 'Resolve', permission: 'incidents.resolve' },
    ],
    investigating: [
        { action: 'escalate', label: 'Escalate', permission: 'incidents.escalate', danger: true },
        { action: 'resolve', label: 'Resolve', permission: 'incidents.resolve' },
    ],
    escalated: [{ action: 'resolve', label: 'Resolve', permission: 'incidents.resolve' }],
    resolved: [{ action: 'close', label: 'Close', permission: 'incidents.update', confirm: true }],
    closed: [],
};

export function IncidentHeader({ org, incident }: { org: string; incident: Incident }) {
    const transition = useIncidentTransition(org, incident.id);
    const available = transitions[incident.status];

    async function run(action: Action, confirm?: boolean) {
        if (confirm && !window.confirm(`Are you sure you want to ${action} this incident?`)) return;
        try {
            await transition.mutateAsync(action);
            toast.success(`Incident ${action === 'investigate' ? 'moved to investigating' : action + 'd'}`);
        } catch (err) {
            toast.error(err instanceof ApiError ? err.message : 'Action failed');
        }
    }

    return (
        <Card className="p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-2">
                    <h1 className="text-xl font-semibold text-content-primary">{incident.title}</h1>
                    <div className="flex flex-wrap items-center gap-3">
                        <IncidentStatusPill status={incident.status} />
                        <SeverityChip severity={incident.severity} />
                        <span className="text-xs text-content-muted">
                            Open for <ElapsedTimer since={incident.opened_at ?? incident.created_at} className="text-content-secondary" />
                        </span>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    {available.map((t) => (
                        <PermissionGate key={t.action} permission={t.permission}>
                            <Button
                                variant={t.danger ? 'danger' : 'secondary'}
                                onClick={() => run(t.action, t.confirm)}
                                loading={transition.isPending}
                            >
                                {t.label}
                            </Button>
                        </PermissionGate>
                    ))}
                </div>
            </div>
        </Card>
    );
}
