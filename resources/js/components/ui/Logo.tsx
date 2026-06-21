/** Sentrix shield mark — navy→teal→cyan, echoing the mobile concept logo. */
export function Logo({ size = 28, withWordmark = false }: { size?: number; withWordmark?: boolean }) {
    return (
        <span className="inline-flex items-center gap-2">
            <svg width={size} height={size} viewBox="0 0 48 48" fill="none" aria-label="Sentrix">
                <defs>
                    <linearGradient id="sx-shield" x1="8" y1="4" x2="40" y2="44" gradientUnits="userSpaceOnUse">
                        <stop stopColor="#0a1f44" />
                        <stop offset="0.55" stopColor="#0e7c7b" />
                        <stop offset="1" stopColor="#3fd3ca" />
                    </linearGradient>
                </defs>
                <path
                    d="M24 3 6 9v13c0 11.3 7.4 19.4 18 23 10.6-3.6 18-11.7 18-23V9L24 3Z"
                    fill="url(#sx-shield)"
                />
                {/* radar arcs + road, in light strokes */}
                <path d="M16 30c2-7 14-7 16 0" stroke="#dffaf6" strokeWidth="2" strokeLinecap="round" opacity="0.85" />
                <path d="M19 24c1.5-4 8.5-4 10 0" stroke="#dffaf6" strokeWidth="2" strokeLinecap="round" opacity="0.6" />
                <path d="M22 34l2-12 2 12" stroke="#ffffff" strokeWidth="2" strokeLinejoin="round" />
            </svg>
            {withWordmark && (
                <span className="text-base font-semibold tracking-wide text-content-primary">
                    SENTRI<span className="text-brand-cyan">X</span>
                </span>
            )}
        </span>
    );
}
