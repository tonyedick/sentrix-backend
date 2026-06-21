import { useState } from 'react';
import { useResponders } from '@/features/responders/api';
import { useOfferResponder } from '@/features/incidents/api';
import { ApiError } from '@/lib/api';
import { SlideOver } from '@/components/ui/SlideOver';
import { Button } from '@/components/ui/Button';
import { PresenceDot } from '@/components/ui/PresenceDot';
import { EmptyState } from '@/components/ui/EmptyState';
import { SkeletonRows } from '@/components/ui/Skeleton';
import { toast } from '@/components/ui/Toast';
import { cn } from '@/lib/cn';
import type { ResponderRole } from '@/types/api';

/**
 * Offer responders to an assignment. Shows assignable responders; excludes any
 * already on this assignment. Picking a responder + role offers them.
 */
export function DispatchSlideOver({
    org,
    incidentId,
    assignmentId,
    excludeResponderIds,
    onClose,
}: {
    org: string;
    incidentId: string;
    assignmentId: string | null;
    excludeResponderIds: string[];
    onClose: () => void;
}) {
    const open = assignmentId !== null;
    const { data, isLoading } = useResponders(org, { status: 'available' });
    const offer = useOfferResponder(org, incidentId);
    const [role, setRole] = useState<ResponderRole>('primary');
    const [pendingId, setPendingId] = useState<string | null>(null);

    const candidates = (data?.data ?? []).filter((r) => r.assignable && !excludeResponderIds.includes(r.id));

    async function dispatch(responderId: string) {
        if (!assignmentId) return;
        setPendingId(responderId);
        try {
            await offer.mutateAsync({ assignmentId, responder_id: responderId, role });
            toast.success('Responder offered');
        } catch (err) {
            toast.error(err instanceof ApiError ? err.message : 'Could not offer responder');
        } finally {
            setPendingId(null);
        }
    }

    return (
        <SlideOver open={open} onClose={onClose} title="Dispatch responders">
            <div className="space-y-4">
                <div className="flex items-center gap-2">
                    <span className="text-xs text-content-secondary">Role</span>
                    {(['primary', 'supporting'] as ResponderRole[]).map((r) => (
                        <button
                            key={r}
                            onClick={() => setRole(r)}
                            className={cn(
                                'rounded-md px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset transition-colors',
                                role === r
                                    ? 'bg-accent/15 text-accent ring-accent/30'
                                    : 'text-content-secondary ring-border-default hover:bg-surface-2',
                            )}
                        >
                            {r}
                        </button>
                    ))}
                </div>

                {isLoading ? (
                    <SkeletonRows rows={5} />
                ) : candidates.length === 0 ? (
                    <EmptyState title="No assignable responders" hint="No available, on-duty responders right now." />
                ) : (
                    <ul className="space-y-2">
                        {candidates.map((r) => (
                            <li
                                key={r.id}
                                className="flex items-center justify-between rounded-md border border-border-subtle bg-surface-2 px-3 py-2"
                            >
                                <div className="flex items-center gap-2">
                                    <PresenceDot tone="success" />
                                    <div className="flex flex-col">
                                        <span className="font-mono text-xs text-content-secondary">{r.id.slice(0, 8)}</span>
                                        <span className="text-[10px] uppercase tracking-wide text-content-muted">
                                            {r.status.replace(/_/g, ' ')}
                                        </span>
                                    </div>
                                </div>
                                <Button
                                    size="sm"
                                    variant="primary"
                                    loading={pendingId === r.id}
                                    onClick={() => dispatch(r.id)}
                                >
                                    Offer
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </SlideOver>
    );
}
