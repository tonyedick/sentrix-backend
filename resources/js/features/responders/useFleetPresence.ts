import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { usePresenceChannel } from '@/realtime/useRealtime';

interface PresenceMember {
    id: string; // user id
}

/**
 * Roster-wide presence: tracks the set of currently connected responder user
 * ids and refreshes the roster query when a status broadcast arrives. Returns
 * the online set so rows can render true connectivity (not just last-seen).
 */
export function useFleetPresence(org: string): { online: Set<string> } {
    const qc = useQueryClient();
    const [online, setOnline] = useState<Set<string>>(new Set());

    usePresenceChannel(`organizations.${org}.responders`, {
        here: (members) => setOnline(new Set((members as PresenceMember[]).map((m) => m.id))),
        joining: (m) => setOnline((prev) => new Set(prev).add((m as PresenceMember).id)),
        leaving: (m) =>
            setOnline((prev) => {
                const next = new Set(prev);
                next.delete((m as PresenceMember).id);
                return next;
            }),
        listen: {
            'responder.status_changed': () => qc.invalidateQueries({ queryKey: ['responders', org] }),
            'responder.registered': () => qc.invalidateQueries({ queryKey: ['responders', org] }),
        },
    });

    return { online };
}
