import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { usePresenceChannel, usePrivateChannel } from '@/realtime/useRealtime';
import { responderKeys } from '@/features/responders/api';

interface PresenceMember {
    id: string;
}

/**
 * Realtime for a single responder's workspace. Tracks presence (is THIS
 * responder connected) and invalidates the detail + location caches when
 * status/location broadcasts arrive. `userId` is the responder's user id, used
 * to match presence membership.
 */
export function useResponderRealtime(org: string, responderId: string, userId: string | null): { online: boolean } {
    const qc = useQueryClient();
    const [onlineIds, setOnlineIds] = useState<Set<string>>(new Set());

    usePresenceChannel(`organizations.${org}.responders`, {
        here: (members) => setOnlineIds(new Set((members as PresenceMember[]).map((m) => m.id))),
        joining: (m) => setOnlineIds((prev) => new Set(prev).add((m as PresenceMember).id)),
        leaving: (m) =>
            setOnlineIds((prev) => {
                const next = new Set(prev);
                next.delete((m as PresenceMember).id);
                return next;
            }),
        listen: {
            'responder.status_changed': (payload: unknown) => {
                const p = payload as { id?: string };
                if (p?.id && p.id !== responderId) return;
                qc.invalidateQueries({ queryKey: responderKeys.detail(org, responderId) });
            },
            'responder.location': () => {
                qc.invalidateQueries({ queryKey: responderKeys.locations(org, responderId) });
                qc.invalidateQueries({ queryKey: responderKeys.detail(org, responderId) });
            },
        },
    });

    // Dispatch activity changes this responder's current assignment + history.
    const refreshAssignmentViews = () => {
        qc.invalidateQueries({ queryKey: responderKeys.detail(org, responderId) }); // current_assignment on show
        qc.invalidateQueries({ queryKey: responderKeys.assignments(org, responderId) });
    };
    usePrivateChannel(`organizations.${org}.assignments`, {
        'assignment.responder_offered': refreshAssignmentViews,
        'assignment.responder_accepted': refreshAssignmentViews,
        'assignment.responder_declined': refreshAssignmentViews,
        'assignment.responder_timed_out': refreshAssignmentViews,
        'assignment.responder_en_route': refreshAssignmentViews,
        'assignment.responder_on_scene': refreshAssignmentViews,
        'assignment.responder_completed': refreshAssignmentViews,
        'assignment.responder_stood_down': refreshAssignmentViews,
        'assignment.cancelled': refreshAssignmentViews,
        'assignment.completed': refreshAssignmentViews,
    });

    return { online: userId ? onlineIds.has(userId) : false };
}
