import { createContext, useContext, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import type Echo from 'laravel-echo';
import { createEcho } from '@/echo';

type ConnectionState = 'connecting' | 'connected' | 'unavailable' | 'disconnected';

interface EchoContextValue {
    echo: Echo<'reverb'> | null;
    connection: ConnectionState;
}

const EchoContext = createContext<EchoContextValue>({ echo: null, connection: 'connecting' });

/**
 * Owns the single Echo/Reverb connection for the authenticated app and exposes
 * live connection state so the shell can surface a "reconnecting" banner.
 */
export function EchoProvider({ children }: { children: ReactNode }) {
    const echoRef = useRef<Echo<'reverb'> | null>(null);
    const [connection, setConnection] = useState<ConnectionState>('connecting');

    if (echoRef.current === null) {
        echoRef.current = createEcho();
    }

    useEffect(() => {
        const echo = echoRef.current;
        // The Reverb/Pusher connector exposes the underlying pusher-js connection.
        const pusher = (echo as unknown as { connector?: { pusher?: { connection?: { bind: (e: string, cb: (s: { current: ConnectionState }) => void) => void; state: ConnectionState } } } }).connector?.pusher?.connection;
        if (!pusher) return;

        setConnection(pusher.state ?? 'connecting');
        pusher.bind('state_change', (states: { current: ConnectionState }) => {
            setConnection(states.current);
        });

        return () => {
            echoRef.current?.disconnect();
            echoRef.current = null;
        };
    }, []);

    const value = useMemo<EchoContextValue>(() => ({ echo: echoRef.current, connection }), [connection]);

    return <EchoContext.Provider value={value}>{children}</EchoContext.Provider>;
}

export function useEcho(): EchoContextValue {
    return useContext(EchoContext);
}
