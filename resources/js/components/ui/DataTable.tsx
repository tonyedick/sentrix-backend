import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { SkeletonRows } from '@/components/ui/Skeleton';
import { EmptyState } from '@/components/ui/EmptyState';

export interface Column<T> {
    key: string;
    header: ReactNode;
    render: (row: T) => ReactNode;
    className?: string;
    headerClassName?: string;
}

export function DataTable<T>({
    columns,
    rows,
    rowKey,
    onRowClick,
    rowClassName,
    isLoading = false,
    emptyTitle = 'Nothing here',
    emptyHint,
}: {
    columns: Column<T>[];
    rows: T[];
    rowKey: (row: T) => string;
    onRowClick?: (row: T) => void;
    rowClassName?: (row: T) => string | undefined;
    isLoading?: boolean;
    emptyTitle?: string;
    emptyHint?: string;
}) {
    if (isLoading) return <SkeletonRows rows={8} className="p-4" />;
    if (rows.length === 0) return <div className="p-4"><EmptyState title={emptyTitle} hint={emptyHint} /></div>;

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead>
                    <tr className="border-b border-border-subtle text-xs uppercase tracking-wide text-content-muted">
                        {columns.map((c) => (
                            <th key={c.key} className={cn('px-4 py-2.5 font-medium', c.headerClassName)}>
                                {c.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={rowKey(row)}
                            onClick={onRowClick ? () => onRowClick(row) : undefined}
                            className={cn(
                                'border-b border-border-subtle/60 transition-colors',
                                onRowClick && 'cursor-pointer hover:bg-surface-2',
                                rowClassName?.(row),
                            )}
                        >
                            {columns.map((c) => (
                                <td key={c.key} className={cn('px-4 py-3 align-middle text-content-secondary', c.className)}>
                                    {c.render(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
