import { Link } from 'react-router-dom';
import { useResponderAssignments } from '@/features/responders/api';
import { Card, CardHeader } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { SkeletonRows } from '@/components/ui/Skeleton';
import { LineStatusPill } from '@/components/ui/StatusPill';

export function AssignmentHistoryPanel({ org, responderId }: { org: string; responderId: string }) {
    const { data, isLoading } = useResponderAssignments(org, responderId);
    const lines = data?.data ?? [];

    return (
        <Card>
            <CardHeader title={`Assignment history${lines.length ? ` · ${lines.length}` : ''}`} />
            <div className="p-4">
                {isLoading ? (
                    <SkeletonRows rows={3} />
                ) : lines.length === 0 ? (
                    <EmptyState title="No assignment history" hint="Dispatches this responder has been part of will appear here." />
                ) : (
                    <ul className="divide-y divide-border-subtle/60">
                        {lines.map((line) => (
                            <li key={line.id} className="flex items-center justify-between gap-2 py-2.5">
                                <div className="min-w-0">
                                    {line.incident ? (
                                        <Link
                                            to={`/${org}/incidents/${line.incident_id}`}
                                            className="block truncate text-sm text-content-primary hover:text-accent"
                                        >
                                            {line.incident.title}
                                        </Link>
                                    ) : (
                                        <span className="text-sm text-content-secondary">Assignment</span>
                                    )}
                                    <span className="text-xs capitalize text-content-muted">
                                        {line.role}
                                        {line.created_at && ` · ${new Date(line.created_at).toLocaleDateString()}`}
                                    </span>
                                </div>
                                <LineStatusPill status={line.status} />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Card>
    );
}
