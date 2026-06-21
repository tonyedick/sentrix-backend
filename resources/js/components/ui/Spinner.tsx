export function Spinner({ className = '' }: { className?: string }) {
    return (
        <svg
            className={`animate-spin ${className}`}
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="3" opacity="0.2" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
        </svg>
    );
}

export function FullPageSpinner() {
    return (
        <div className="flex h-full min-h-screen items-center justify-center bg-surface-0 text-accent">
            <Spinner className="size-6" />
        </div>
    );
}
