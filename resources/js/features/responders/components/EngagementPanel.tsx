import { Link } from 'react-router-dom';
import { Card, CardHeader } from '@/components/ui/Card';
import { ResponderStatusPill, LineStatusPill, IncidentStatusPill } from '@/components/ui/StatusPill';
import type { Responder, ResponderStatus } from '@/types/api';

const ENGAGED: ResponderStatus[] = ['on_assignment', 'en_route', 'on_scene'];

/**
 * Current assignment load. Uses the responder's current_assignment (from
 * responders.show) when present, linking straight to the incident; otherwise
 * falls back to engagement state derived from status.
 */
export function EngagementPanel({ org, responder }: { org: string; responder: Responder }) {
    const current = responder.current_assignment ?? null;
    const engaged = current !== null || ENGAGED.includes(responder.status);

    return (
        <Card>
            <CardHeader title="Current engagement" />
            <div className="space-y-3 p-4">
                <div className="flex items-center gap-3">
                    <span
                        className={`flex size-10 items-center justify-center rounded-full text-xs font-semibold ${
                            engaged ? 'bg-status-info/15 text-status-info' : 'bg-status-success/15 text-status-success'
                        }`}
                    >
                        {engaged ? 'ON' : 'IDLE'}
                    </span>
                    <div>
                        <p className="text-sm font-medium text-content-primary">
                            {engaged ? 'Currently engaged' : responder.status === 'available' ? 'Ready for dispatch' : 'Not engaged'}
                        </p>
                        <div className="mt-0.5 flex items-center gap-2">
                            <ResponderStatusPill status={responder.status} />
                            <span className="text-xs text-content-muted">{responder.assignable ? 'assignable' : 'not assignable'}</span>
                        </div>
                    </div>
                </div>

                {current && (
                    <Link
                        to={`/${org}/incidents/${current.incident_id}`}
                        className="block rounded-md border border-border-subtle bg-surface-2 px-3 py-2.5 transition-colors hover:border-border-strong hover:bg-surface-3"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-sm font-medium text-content-primary">
                                {current.incident?.title ?? 'Active assignment'}
                            </span>
                            {current.incident && <IncidentStatusPill status={current.incident.status} />}
                        </div>
                        <div className="mt-1.5 flex items-center gap-2 text-xs text-content-muted">
                            <span className="capitalize">{current.role}</span>
                            <span>·</span>
                            <LineStatusPill status={current.status} />
                            <span className="ml-auto text-accent">Open incident →</span>
                        </div>
                    </Link>
                )}
            </div>
        </Card>
    );
}
