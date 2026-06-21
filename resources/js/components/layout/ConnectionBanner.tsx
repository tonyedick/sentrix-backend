import { useEcho } from '@/realtime/EchoProvider';

/** Amber strip when realtime drops, so operators know data may be stale. */
export function ConnectionBanner() {
    const { connection } = useEcho();
    if (connection === 'connected') return null;

    const message =
        connection === 'connecting'
            ? 'Connecting to live updates…'
            : 'Live updates interrupted — reconnecting. Data may be stale.';

    return (
        <div className="flex items-center gap-2 bg-status-warn/15 px-4 py-1.5 text-xs text-status-warn">
            <span className="inline-block size-1.5 animate-pulse rounded-full bg-status-warn" />
            {message}
        </div>
    );
}
