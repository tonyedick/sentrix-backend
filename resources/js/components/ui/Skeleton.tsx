import { cn } from '@/lib/cn';

export function Skeleton({ className }: { className?: string }) {
    return <div className={cn('animate-pulse rounded bg-surface-3', className)} />;
}

export function SkeletonRows({ rows = 6, className }: { rows?: number; className?: string }) {
    return (
        <div className={cn('space-y-2', className)}>
            {Array.from({ length: rows }).map((_, i) => (
                <Skeleton key={i} className="h-12 w-full" />
            ))}
        </div>
    );
}
