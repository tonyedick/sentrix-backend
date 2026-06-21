import { useSearchParams } from 'react-router-dom';
import {
    useNotifications,
    useMarkNotificationRead,
    useMarkAllNotificationsRead,
} from '@/features/notifications/api';
import { Card, CardHeader } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { SkeletonRows } from '@/components/ui/Skeleton';
import { EmptyState } from '@/components/ui/EmptyState';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { ElapsedTimer } from '@/components/ui/ElapsedTimer';
import { cn } from '@/lib/cn';
import type { NotificationItem } from '@/types/api';

const FILTERS = [
    { value: '', label: 'All' },
    { value: 'unread', label: 'Unread' },
];

function title(n: NotificationItem): string {
    const t = (n.payload?.title as string | undefined) ?? n.type;
    return String(t).replace(/[._]/g, ' ');
}

export function NotificationCenterPage() {
    const [params, setParams] = useSearchParams();
    const unreadOnly = params.get('filter') === 'unread';

    const { data, isLoading } = useNotifications(unreadOnly);
    const markRead = useMarkNotificationRead();
    const markAll = useMarkAllNotificationsRead();

    const rows = data?.data ?? [];

    function setFilter(value: string) {
        const next = new URLSearchParams(params);
        if (value) next.set('filter', value);
        else next.delete('filter');
        setParams(next, { replace: true });
    }

    return (
        <div className="mx-auto max-w-3xl space-y-4 p-4 lg:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-lg font-semibold text-content-primary">Notifications</h1>
                <Button variant="ghost" size="sm" onClick={() => markAll.mutate()} loading={markAll.isPending}>
                    Mark all read
                </Button>
            </div>

            <SegmentedControl segments={FILTERS} value={unreadOnly ? 'unread' : ''} onChange={setFilter} />

            <Card>
                <CardHeader title={`Inbox${rows.length ? ` · ${rows.length}` : ''}`} />
                {isLoading ? (
                    <SkeletonRows rows={6} className="p-4" />
                ) : rows.length === 0 ? (
                    <div className="p-4"><EmptyState title={unreadOnly ? 'No unread notifications' : 'No notifications'} /></div>
                ) : (
                    <ul className="divide-y divide-border-subtle/60">
                        {rows.map((n) => (
                            <li
                                key={n.id}
                                className={cn('flex items-start gap-3 px-4 py-3', !n.read && 'bg-accent/[0.04]')}
                            >
                                <span className={cn('mt-1.5 size-2 shrink-0 rounded-full', n.read ? 'bg-border-default' : 'bg-accent')} />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium capitalize text-content-primary">{title(n)}</p>
                                    {n.payload?.message ? (
                                        <p className="truncate text-xs text-content-muted">{String(n.payload.message)}</p>
                                    ) : null}
                                    <time className="tabular text-[11px] text-content-muted">
                                        <ElapsedTimer since={n.created_at} />
                                    </time>
                                </div>
                                {!n.read && (
                                    <button
                                        onClick={() => markRead.mutate(n.id)}
                                        className="shrink-0 text-xs text-accent hover:text-accent-hover"
                                    >
                                        Mark read
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </div>
    );
}
