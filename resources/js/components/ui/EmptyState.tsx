import type { ReactNode } from 'react';

export function EmptyState({ title, hint, action }: { title: string; hint?: string; action?: ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border-default bg-surface-1/40 px-6 py-12 text-center">
            <p className="text-sm font-medium text-content-secondary">{title}</p>
            {hint && <p className="max-w-sm text-xs text-content-muted">{hint}</p>}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
