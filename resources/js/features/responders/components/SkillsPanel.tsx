import { useResponderSkills } from '@/features/responders/api';
import { Card, CardHeader } from '@/components/ui/Card';
import { Badge } from '@/components/ui/StatusPill';
import { SkeletonRows } from '@/components/ui/Skeleton';
import { EmptyState } from '@/components/ui/EmptyState';

const proficiencyTone = (p?: string | null) =>
    p === 'expert' ? 'success' : p === 'trained' ? 'accent' : 'neutral';

export function SkillsPanel({ org, responderId }: { org: string; responderId: string }) {
    const { data, isLoading } = useResponderSkills(org, responderId);

    return (
        <Card>
            <CardHeader title={`Skills${data?.length ? ` · ${data.length}` : ''}`} />
            <div className="p-4">
                {isLoading ? (
                    <SkeletonRows rows={2} />
                ) : !data || data.length === 0 ? (
                    <EmptyState title="No skills assigned" />
                ) : (
                    <ul className="flex flex-wrap gap-1.5">
                        {data.map((s) => (
                            <li key={s.id}>
                                <Badge tone={proficiencyTone(s.proficiency)}>
                                    {s.name}
                                    {s.proficiency ? ` · ${s.proficiency}` : ''}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Card>
    );
}
