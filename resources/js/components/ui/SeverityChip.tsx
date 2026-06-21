import { cn } from '@/lib/cn';
import type { IncidentSeverity } from '@/types/api';

const ramp: Record<IncidentSeverity, { dot: string; text: string; rank: number }> = {
    low: { dot: 'bg-severity-low', text: 'text-content-secondary', rank: 1 },
    medium: { dot: 'bg-severity-medium', text: 'text-severity-medium', rank: 2 },
    high: { dot: 'bg-severity-high', text: 'text-severity-high', rank: 3 },
    critical: { dot: 'bg-severity-critical', text: 'text-severity-critical', rank: 4 },
};

export function SeverityChip({ severity, className }: { severity: IncidentSeverity; className?: string }) {
    const s = ramp[severity];
    return (
        <span className={cn('inline-flex items-center gap-1.5 text-xs font-medium capitalize', s.text, className)}>
            <span className="inline-flex gap-0.5" aria-hidden="true">
                {[1, 2, 3, 4].map((i) => (
                    <span
                        key={i}
                        className={cn('h-3 w-1 rounded-sm', i <= s.rank ? s.dot : 'bg-border-default')}
                    />
                ))}
            </span>
            {severity}
        </span>
    );
}
