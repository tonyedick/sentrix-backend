import type { ReactNode } from 'react';

/* Minimal inline icon set (stroke-based, inherit currentColor). */
type IconProps = { className?: string };
const base = (children: ReactNode) => ({ className }: IconProps) => (
    <svg className={className} width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        {children}
    </svg>
);

export const GridIcon = base(
    <>
        <rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.8" />
        <rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.8" />
        <rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.8" />
        <rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.8" />
    </>,
);

export const AlertIcon = base(
    <>
        <path d="M12 3 2 20h20L12 3Z" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" />
        <path d="M12 10v4" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
        <circle cx="12" cy="17" r="1" fill="currentColor" />
    </>,
);

export const UsersIcon = base(
    <>
        <circle cx="9" cy="8" r="3" stroke="currentColor" strokeWidth="1.8" />
        <path d="M3 20a6 6 0 0 1 12 0" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
        <path d="M16 5.5a3 3 0 0 1 0 5.8M17 20a6 6 0 0 0-3-5.2" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
    </>,
);

export const SettingsIcon = base(
    <>
        <circle cx="12" cy="12" r="3" stroke="currentColor" strokeWidth="1.8" />
        <path
            d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
        />
    </>,
);

export const SearchIcon = base(
    <>
        <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="1.8" />
        <path d="m20 20-3.5-3.5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
    </>,
);

export const ChevronIcon = base(<path d="m6 9 6 6 6-6" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />);

export const SirenIcon = base(
    <>
        <path d="M5 18a7 7 0 0 1 14 0v1H5v-1Z" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" />
        <path d="M12 4v3M4 9 6 10M20 9l-2 1" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
        <path d="M3 22h18" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
    </>,
);

export const EscalationIcon = base(
    <>
        <path d="M3 17l6-6 4 4 8-8" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
        <path d="M15 7h6v6" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
    </>,
);

export const BellIcon = base(
    <>
        <path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6Z" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" />
        <path d="M10 20a2 2 0 0 0 4 0" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
    </>,
);
