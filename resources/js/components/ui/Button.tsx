import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { Spinner } from '@/components/ui/Spinner';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'sm' | 'md';

const variantClass: Record<Variant, string> = {
    primary: 'bg-accent text-accent-fg hover:bg-accent-hover',
    secondary: 'bg-surface-2 text-content-primary ring-1 ring-inset ring-border-default hover:bg-surface-3',
    ghost: 'text-content-secondary hover:bg-surface-2 hover:text-content-primary',
    danger: 'bg-status-danger/15 text-status-danger ring-1 ring-inset ring-status-danger/30 hover:bg-status-danger/25',
};

const sizeClass: Record<Size, string> = {
    sm: 'h-7 px-2.5 text-xs gap-1.5',
    md: 'h-9 px-3.5 text-sm gap-2',
};

export function Button({
    variant = 'secondary',
    size = 'md',
    loading = false,
    icon,
    className,
    children,
    disabled,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
    icon?: ReactNode;
}) {
    return (
        <button
            className={cn(
                'inline-flex items-center justify-center rounded-md font-medium transition-colors',
                'disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-accent',
                variantClass[variant],
                sizeClass[size],
                className,
            )}
            disabled={disabled || loading}
            {...props}
        >
            {loading ? <Spinner className="size-4" /> : icon}
            {children}
        </button>
    );
}
