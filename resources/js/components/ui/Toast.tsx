import { create } from 'zustand';
import { cn } from '@/lib/cn';

type ToastTone = 'success' | 'error' | 'info';
interface Toast {
    id: number;
    tone: ToastTone;
    message: string;
}

interface ToastState {
    toasts: Toast[];
    push: (tone: ToastTone, message: string) => void;
    dismiss: (id: number) => void;
}

let counter = 0;

const useToastStore = create<ToastState>((set) => ({
    toasts: [],
    push: (tone, message) => {
        const id = ++counter;
        set((s) => ({ toasts: [...s.toasts, { id, tone, message }] }));
        window.setTimeout(() => set((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) })), 4500);
    },
    dismiss: (id) => set((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) })),
}));

/** Imperative helper usable outside React render (e.g. mutation callbacks). */
export const toast = {
    success: (m: string) => useToastStore.getState().push('success', m),
    error: (m: string) => useToastStore.getState().push('error', m),
    info: (m: string) => useToastStore.getState().push('info', m),
};

const toneClass: Record<ToastTone, string> = {
    success: 'border-status-success/40 text-content-primary',
    error: 'border-status-danger/40 text-content-primary',
    info: 'border-border-strong text-content-primary',
};

export function ToastViewport() {
    const toasts = useToastStore((s) => s.toasts);
    const dismiss = useToastStore((s) => s.dismiss);
    return (
        <div className="pointer-events-none fixed bottom-4 right-4 z-50 flex w-80 flex-col gap-2">
            {toasts.map((t) => (
                <button
                    key={t.id}
                    onClick={() => dismiss(t.id)}
                    className={cn(
                        'pointer-events-auto rounded-lg border bg-surface-2 px-3.5 py-2.5 text-left text-sm shadow-lg shadow-black/30',
                        toneClass[t.tone],
                    )}
                >
                    {t.message}
                </button>
            ))}
        </div>
    );
}
