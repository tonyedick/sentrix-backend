import { cn } from '@/lib/cn';

export interface Segment {
    value: string;
    label: string;
    count?: number;
}

/** Compact segmented control for quick, single-select filtering (e.g. status). */
export function SegmentedControl({
    segments,
    value,
    onChange,
}: {
    segments: Segment[];
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="inline-flex flex-wrap gap-1 rounded-lg border border-border-subtle bg-surface-1 p-1">
            {segments.map((s) => {
                const active = s.value === value;
                return (
                    <button
                        key={s.value || 'all'}
                        onClick={() => onChange(s.value)}
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium capitalize transition-colors',
                            active
                                ? 'bg-surface-3 text-content-primary'
                                : 'text-content-muted hover:text-content-secondary',
                        )}
                    >
                        {s.label}
                        {s.count !== undefined && (
                            <span className={cn('tabular text-[10px]', active ? 'text-content-secondary' : 'text-content-muted')}>
                                {s.count}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
