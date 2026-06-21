/** Central runtime config for the Operations Dashboard SPA. */
export const config = {
    apiBase: '/api/v1',
    csrfCookieUrl: '/sanctum/csrf-cookie',
    broadcastAuthEndpoint: '/broadcasting/auth',
    reverb: {
        key: import.meta.env.VITE_REVERB_APP_KEY as string | undefined,
        host: import.meta.env.VITE_REVERB_HOST as string | undefined,
        port: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        scheme: (import.meta.env.VITE_REVERB_SCHEME as string | undefined) ?? 'http',
    },
} as const;
