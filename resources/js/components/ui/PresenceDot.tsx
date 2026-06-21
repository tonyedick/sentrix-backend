import { cn } from '@/lib/cn';

type Tone = 'success' | 'warn' | 'danger' | 'neutral' | 'accent';

const toneClass: Record<Tone, string> = {
    success: 'bg-status-success',
    warn: 'bg-status-warn',
    danger: 'bg-status-danger',
    neutral: 'bg-content-muted',
    accent: 'bg-accent',
};

export function PresenceDot({ tone = 'neutral', pulse = false }: { tone?: Tone; pulse?: boolean }) {
    return (
        <span className="relative inline-flex size-2.5 shrink-0">
            {pulse && (
                <span className={cn('absolute inline-flex size-full animate-ping rounded-full opacity-60', toneClass[tone])} />
            )}
            <span className={cn('relative inline-flex size-2.5 rounded-full', toneClass[tone])} />
        </span>
    );
}
