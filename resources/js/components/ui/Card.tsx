import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export function Card({ className, children }: { className?: string; children: ReactNode }) {
    return <div className={cn('rounded-lg border border-border-subtle bg-surface-1', className)}>{children}</div>;
}

export function CardHeader({ title, action, className }: { title: ReactNode; action?: ReactNode; className?: string }) {
    return (
        <div className={cn('flex items-center justify-between border-b border-border-subtle px-4 py-3', className)}>
            <h2 className="text-sm font-semibold text-content-primary">{title}</h2>
            {action}
        </div>
    );
}
