import { useEffect } from 'react';
import { useEcho } from '@/realtime/EchoProvider';

type EventHandlers = Record<string, (payload: unknown) => void>;

/**
 * Subscribe to a private org channel and bind dotted broadcast events
 * (e.g. `.incident.opened`). Automatically leaves the channel on unmount or
 * when the channel name changes. `enabled` lets callers gate on auth/org.
 */
export function usePrivateChannel(channel: string | null, handlers: EventHandlers, enabled = true): void {
    const { echo } = useEcho();

    useEffect(() => {
        if (!echo || !channel || !enabled) return;

        const subscription = echo.private(channel);
        for (const [event, handler] of Object.entries(handlers)) {
            subscription.listen('.' + event, handler);
        }

        return () => {
            echo.leave(channel);
        };
        // handlers identity is owned by the caller (usually stable refs / inline ok for Phase 1)
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [echo, channel, enabled]);
}

interface PresenceOptions {
    here?: (members: unknown[]) => void;
    joining?: (member: unknown) => void;
    leaving?: (member: unknown) => void;
    listen?: EventHandlers;
}

/** Subscribe to a presence channel (roster + optional broadcast events). */
export function usePresenceChannel(channel: string | null, options: PresenceOptions, enabled = true): void {
    const { echo } = useEcho();

    useEffect(() => {
        if (!echo || !channel || !enabled) return;

        const presence = echo.join(channel);
        if (options.here) presence.here(options.here);
        if (options.joining) presence.joining(options.joining);
        if (options.leaving) presence.leaving(options.leaving);
        if (options.listen) {
            for (const [event, handler] of Object.entries(options.listen)) {
                presence.listen('.' + event, handler);
            }
        }

        return () => {
            echo.leave(channel);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [echo, channel, enabled]);
}
