import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { config } from '@/config';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: Echo<'reverb'>;
    }
}

window.Pusher = Pusher;

/**
 * Lazily create the Echo/Reverb client. Private + presence channels authenticate
 * against /broadcasting/auth using the same Sanctum cookie session as the API,
 * so the CSRF header is forwarded on the auth POST.
 */
export function createEcho(): Echo<'reverb'> {
    function readCookie(name: string): string | undefined {
        const m = document.cookie.match(new RegExp('(^|; )' + name + '=([^;]*)'));
        return m ? decodeURIComponent(m[2]) : undefined;
    }

    return new Echo<'reverb'>({
        broadcaster: 'reverb',
        key: config.reverb.key,
        wsHost: config.reverb.host,
        wsPort: config.reverb.port,
        wssPort: config.reverb.port,
        forceTLS: config.reverb.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: config.broadcastAuthEndpoint,
        auth: {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '',
            },
        },
    });
}
