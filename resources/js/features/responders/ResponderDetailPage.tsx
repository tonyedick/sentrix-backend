import { Link, useParams } from 'react-router-dom';
import { useResponder } from '@/features/responders/api';
import { useResponderRealtime } from '@/features/responders/useResponderRealtime';
import { useAuth } from '@/auth/AuthProvider';
import { ResponderStatusControl } from '@/features/responders/components/ResponderStatusControl';
import { EngagementPanel } from '@/features/responders/components/EngagementPanel';
import { LocationPanel } from '@/features/responders/components/LocationPanel';
import { CertificationsPanel } from '@/features/responders/components/CertificationsPanel';
import { SkillsPanel } from '@/features/responders/components/SkillsPanel';
import { AssignmentHistoryPanel } from '@/features/responders/components/AssignmentHistoryPanel';
import { Card } from '@/components/ui/Card';
import { ResponderStatusPill } from '@/components/ui/StatusPill';
import { PresenceDot } from '@/components/ui/PresenceDot';
import { Freshness } from '@/components/ui/Freshness';
import { SkeletonRows } from '@/components/ui/Skeleton';
import { EmptyState } from '@/components/ui/EmptyState';

export function ResponderDetailPage() {
    const { org = '', responderId = '' } = useParams();
    const { user, can } = useAuth();
    const { data, isLoading, isError } = useResponder(org, responderId);
    const { online } = useResponderRealtime(org, responderId, data?.user_id ?? null);

    if (isLoading) return <div className="p-6"><SkeletonRows rows={8} /></div>;
    if (isError || !data) {
        return (
            <div className="p-6">
                <EmptyState title="Responder not available" hint="They may have been removed or you may not have access." />
            </div>
        );
    }

    const isSelf = user?.id === data.user_id;
    const canManage = can('responders.manage') || (isSelf && can('responders.self'));

    return (
        <div className="mx-auto max-w-7xl space-y-4 p-4 lg:p-6">
            <Link to={`/${org}/responders`} className="text-xs text-content-muted hover:text-content-secondary">
                ← Back to responders
            </Link>

            <Card className="p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <PresenceDot tone={online ? 'success' : 'neutral'} pulse={online} />
                        <div>
                            <h1 className="font-mono text-lg font-semibold text-content-primary">{data.user_id.slice(0, 8)}</h1>
                            <div className="mt-1 flex flex-wrap items-center gap-2">
                                <ResponderStatusPill status={data.status} />
                                <span className="text-xs text-content-muted">{data.on_duty ? 'On duty' : 'Off duty'}</span>
                                <span className="text-xs text-content-muted">·</span>
                                <span className="text-xs text-content-muted">{online ? 'connected' : 'disconnected'}</span>
                                <span className="text-xs text-content-muted">·</span>
                                <Freshness at={data.last_seen_at} />
                            </div>
                        </div>
                    </div>

                    {canManage && <ResponderStatusControl org={org} responder={data} />}
                </div>
            </Card>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    <EngagementPanel org={org} responder={data} />
                    <CertificationsPanel org={org} responderId={data.id} />
                    <AssignmentHistoryPanel org={org} responderId={data.id} />
                </div>
                <div className="space-y-4 lg:col-span-1">
                    <LocationPanel org={org} responder={data} />
                    <SkillsPanel org={org} responderId={data.id} />
                </div>
            </div>
        </div>
    );
}
