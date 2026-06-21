import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

/**
 * Right-anchored panel that keeps the operator in context (dispatch, detail).
 * On small screens it expands to a near-full-width sheet.
 */
export function SlideOver({
    open,
    onClose,
    title,
    children,
    width = 'max-w-md',
}: {
    open: boolean;
    onClose: () => void;
    title: ReactNode;
    children: ReactNode;
    width?: string;
}) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-40">
            <div className="absolute inset-0 bg-black/50" onClick={onClose} aria-hidden="true" />
            <div
                role="dialog"
                aria-modal="true"
                className={cn(
                    'absolute right-0 top-0 flex h-full w-full flex-col border-l border-border-default bg-surface-1 shadow-2xl',
                    width,
                )}
            >
                <div className="flex items-center justify-between border-b border-border-subtle px-4 py-3">
                    <h2 className="text-sm font-semibold text-content-primary">{title}</h2>
                    <button
                        onClick={onClose}
                        className="rounded p-1 text-content-muted hover:bg-surface-2 hover:text-content-primary"
                        aria-label="Close"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                        </svg>
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto p-4">{children}</div>
            </div>
        </div>
    );
}
