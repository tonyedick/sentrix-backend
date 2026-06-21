import { useState } from 'react';
import { useEnsureAssignment, useCancelAssignment } from '@/features/incidents/api';
import { ApiError } from '@/lib/api';
import { PermissionGate } from '@/auth/PermissionGate';
import { Card, CardHeader } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { AssignmentStatusPill, LineStatusPill, Badge } from '@/components/ui/StatusPill';
import { ElapsedTimer } from '@/components/ui/ElapsedTimer';
import { toast } from '@/components/ui/Toast';
import { DispatchSlideOver } from '@/features/incidents/components/DispatchSlideOver';
import type { Assignment, AssignmentResponder, IncidentStatus } from '@/types/api';

export function AssignmentPanel({
    org,
    incidentId,
    assignment,
    incidentStatus,
}: {
    org: string;
    incidentId: string;
    assignment: Assignment | null;
    incidentStatus: IncidentStatus;
}) {
    const ensureAssignment = useEnsureAssignment(org, incidentId);
    const cancelAssignment = useCancelAssignment(org, incidentId);
    const [dispatchFor, setDispatchFor] = useState<string | null>(null);

    const terminal = incidentStatus === 'resolved' || incidentStatus === 'closed';
    const lines = assignment?.responders ?? [];
    const offeredIds = lines.map((l) => l.responder_id);

    async function startDispatch() {
        try {
            const id = assignment?.id ?? (await ensureAssignment.mutateAsync()).id;
            setDispatchFor(id);
        } catch (err) {
            toast.error(err instanceof ApiError ? err.message : 'Could not start dispatch');
        }
    }

    async function cancel() {
        if (!assignment) return;
        if (!window.confirm('Cancel this dispatch and release all responders?')) return;
        try {
            await cancelAssignment.mutateAsync(assignment.id);
            toast.success('Dispatch cancelled');
        } catch (err) {
            toast.error(err instanceof ApiError ? err.message : 'Cancel failed');
        }
    }

    return (
        <Card>
            <CardHeader
                title="Dispatch"
                action={assignment ? <AssignmentStatusPill status={assignment.status} /> : undefined}
            />

            <div className="space-y-3 p-4">
                {assignment && (
                    <div className="flex items-center gap-4 text-xs text-content-muted">
                        <span>
                            Primary <span className="text-content-secondary">{countByRole(lines, 'primary')}/{assignment.required_primary}</span>
                        </span>
                        <span>
                            Supporting{' '}
                            <span className="text-content-secondary">
                                {countByRole(lines, 'supporting')}/{assignment.required_supporting}
                            </span>
                        </span>
                        {assignment.escalation_level > 0 && <Badge tone="danger">esc L{assignment.escalation_level}</Badge>}
                    </div>
                )}

                {lines.length === 0 ? (
                    <EmptyState title={terminal ? 'No responders were dispatched' : 'No responders dispatched yet'} />
                ) : (
                    <ul className="space-y-2">
                        {lines.map((line) => (
                            <LineRow key={line.id} line={line} deadline={assignment?.acceptance_deadline_at} />
                        ))}
                    </ul>
                )}

                {!terminal && (
                    <div className="flex flex-wrap gap-2 pt-1">
                        <PermissionGate anyOf={['assignments.create', 'assignments.dispatch']}>
                            <Button variant="primary" onClick={startDispatch} loading={ensureAssignment.isPending}>
                                {lines.length ? 'Add responder' : 'Dispatch responders'}
                            </Button>
                        </PermissionGate>
                        {assignment && (
                            <PermissionGate permission="assignments.cancel">
                                <Button variant="ghost" onClick={cancel} loading={cancelAssignment.isPending}>
                                    Cancel dispatch
                                </Button>
                            </PermissionGate>
                        )}
                    </div>
                )}
            </div>

            <DispatchSlideOver
                org={org}
                incidentId={incidentId}
                assignmentId={dispatchFor}
                excludeResponderIds={offeredIds}
                onClose={() => setDispatchFor(null)}
            />
        </Card>
    );
}

function LineRow({ line, deadline }: { line: AssignmentResponder; deadline?: string | null }) {
    return (
        <li className="flex items-center justify-between rounded-md border border-border-subtle bg-surface-2 px-3 py-2">
            <div className="flex flex-col">
                <span className="font-mono text-xs text-content-secondary">{line.responder_id.slice(0, 8)}</span>
                <span className="text-[10px] uppercase tracking-wide text-content-muted">{line.role}</span>
            </div>
            <div className="flex items-center gap-2">
                {line.status === 'offered' && deadline && <ElapsedTimer deadline={deadline} />}
                <LineStatusPill status={line.status} />
            </div>
        </li>
    );
}

function countByRole(lines: AssignmentResponder[], role: 'primary' | 'supporting'): number {
    const committed = ['accepted', 'en_route', 'on_scene', 'completed'];
    return lines.filter((l) => l.role === role && committed.includes(l.status)).length;
}
