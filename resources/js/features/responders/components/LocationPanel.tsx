import { useResponderLocations } from '@/features/responders/api';
import { Card, CardHeader } from '@/components/ui/Card';
import { Freshness } from '@/components/ui/Freshness';
import { SkeletonRows } from '@/components/ui/Skeleton';
import { EmptyState } from '@/components/ui/EmptyState';
import type { Responder } from '@/types/api';

function coord(n: number | null): string {
    return n === null ? '—' : n.toFixed(5);
}

export function LocationPanel({ org, responder }: { org: string; responder: Responder }) {
    const { data, isLoading } = useResponderLocations(org, responder.id);
    const recent = (data ?? []).slice(0, 8);

    return (
        <Card>
            <CardHeader
                title="Location status"
                action={<Freshness at={responder.last_seen_at} />}
            />
            <div className="space-y-3 p-4">
                <div className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p className="text-xs uppercase tracking-wide text-content-muted">Last latitude</p>
                        <p className="tabular text-content-secondary">{coord(responder.last_lat)}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide text-content-muted">Last longitude</p>
                        <p className="tabular text-content-secondary">{coord(responder.last_lng)}</p>
                    </div>
                </div>

                <div>
                    <p className="mb-1 text-xs uppercase tracking-wide text-content-muted">Recent fixes</p>
                    {isLoading ? (
                        <SkeletonRows rows={3} />
                    ) : recent.length === 0 ? (
                        <EmptyState title="No location history" />
                    ) : (
                        <ul className="divide-y divide-border-subtle/60 rounded-md border border-border-subtle">
                            {recent.map((loc) => (
                                <li key={loc.id} className="flex items-center justify-between px-3 py-1.5 text-xs">
                                    <span className="tabular text-content-secondary">
                                        {loc.lat.toFixed(4)}, {loc.lng.toFixed(4)}
                                    </span>
                                    <time className="tabular text-content-muted">
                                        {loc.recorded_at ? new Date(loc.recorded_at).toLocaleTimeString() : '—'}
                                    </time>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </Card>
    );
}
