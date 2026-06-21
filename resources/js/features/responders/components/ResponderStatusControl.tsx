import { useUpdateResponderStatus } from '@/features/responders/api';
import { ApiError } from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { toast } from '@/components/ui/Toast';
import type { Responder, ResponderStatus } from '@/types/api';

const QUICK: { status: ResponderStatus; label: string; variant: 'primary' | 'secondary' | 'danger' }[] = [
    { status: 'available', label: 'Available', variant: 'primary' },
    { status: 'unavailable', label: 'Unavailable', variant: 'secondary' },
    { status: 'off_duty', label: 'Off duty', variant: 'secondary' },
];

const ALL_STATUSES: ResponderStatus[] = [
    'available',
    'unavailable',
    'on_assignment',
    'en_route',
    'on_scene',
    'off_duty',
    'suspended',
];

/**
 * Availability + duty management. Quick buttons for common transitions plus a
 * full dropdown. Optimistic; server enforces the state machine + permission
 * (managing another responder requires responders.manage).
 */
export function ResponderStatusControl({ org, responder }: { org: string; responder: Responder }) {
    const update = useUpdateResponderStatus(org);

    async function set(status: ResponderStatus) {
        if (status === responder.status) return;
        if (status === 'suspended' && !window.confirm('Suspend this responder? They will not be assignable.')) return;
        try {
            await update.mutateAsync({ responderId: responder.id, status });
            toast.success(`Status set to ${status.replace(/_/g, ' ')}`);
        } catch (err) {
            toast.error(err instanceof ApiError ? err.message : 'Could not change status');
        }
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            {QUICK.map((q) => (
                <Button
                    key={q.status}
                    size="sm"
                    variant={responder.status === q.status ? 'secondary' : q.variant}
                    disabled={responder.status === q.status || update.isPending}
                    onClick={() => set(q.status)}
                >
                    {q.label}
                </Button>
            ))}
            <select
                value={responder.status}
                onChange={(e) => set(e.target.value as ResponderStatus)}
                className="rounded-md border border-border-default bg-surface-2 px-2 py-1.5 text-xs capitalize text-content-secondary focus:outline-accent"
                aria-label="Set status"
            >
                {ALL_STATUSES.map((s) => (
                    <option key={s} value={s}>
                        {s.replace(/_/g, ' ')}
                    </option>
                ))}
            </select>
        </div>
    );
}
