import { useEffect, useState } from 'react';
import { SearchIcon } from '@/components/layout/icons';

/**
 * Debounced search box. Reports the debounced value upward; keeps its own
 * immediate input state so typing stays responsive.
 */
export function SearchInput({
    value,
    onChange,
    placeholder = 'Search…',
    debounceMs = 200,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    debounceMs?: number;
}) {
    const [local, setLocal] = useState(value);

    useEffect(() => setLocal(value), [value]);

    useEffect(() => {
        const id = window.setTimeout(() => {
            if (local !== value) onChange(local);
        }, debounceMs);
        return () => window.clearTimeout(id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [local, debounceMs]);

    return (
        <div className="relative">
            <SearchIcon className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-content-muted" />
            <input
                value={local}
                onChange={(e) => setLocal(e.target.value)}
                placeholder={placeholder}
                className="w-full rounded-md border border-border-default bg-surface-2 py-1.5 pl-8 pr-3 text-sm text-content-primary placeholder:text-content-muted focus:outline-accent"
            />
            {local && (
                <button
                    onClick={() => {
                        setLocal('');
                        onChange('');
                    }}
                    className="absolute right-2 top-1/2 -translate-y-1/2 text-content-muted hover:text-content-secondary"
                    aria-label="Clear search"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                    </svg>
                </button>
            )}
        </div>
    );
}
