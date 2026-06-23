import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClientProvider } from '@tanstack/react-query';
import { queryClient } from '@/lib/queryClient';
import { AuthProvider } from '@/auth/AuthProvider';
import { EchoProvider } from '@/realtime/EchoProvider';
import { AppRouter } from '@/router';
import { ToastViewport } from '@/components/ui/Toast';
import '@/echo';
import '../css/app.css';
import { configureEcho } from '@laravel/echo-react';

configureEcho({
    broadcaster: 'reverb',
});

const container = document.getElementById('app');
if (container) {
    createRoot(container).render(
        <StrictMode>
            <QueryClientProvider client={queryClient}>
                <AuthProvider>
                    <EchoProvider>
                        <AppRouter />
                        <ToastViewport />
                    </EchoProvider>
                </AuthProvider>
            </QueryClientProvider>
        </StrictMode>,
    );
}
