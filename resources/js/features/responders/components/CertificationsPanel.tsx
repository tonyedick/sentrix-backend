import { useResponderCertifications, useVerifyCertification } from '@/features/responders/api';
import { ApiError } from '@/lib/api';
import { PermissionGate } from '@/auth/PermissionGate';
import { Card, CardHeader } from '@/components/ui/Card';
import { Badge } from '@/components/ui/StatusPill';
import type { Tone } from '@/components/ui/StatusPill';
import { Button } from '@/components/ui/Button';
import { SkeletonRows } from '@/components/ui/Skeleton';
import { EmptyState } from '@/components/ui/EmptyState';
import { toast } from '@/components/ui/Toast';
import type { CertificationStatus } from '@/types/api';

const certTone: Record<CertificationStatus, Tone> = {
    valid: 'success',
    expiring: 'warn',
    expired: 'danger',
    revoked: 'danger',
    pending: 'neutral',
};

export function CertificationsPanel({ org, responderId }: { org: string; responderId: string }) {
    const { data, isLoading } = useResponderCertifications(org, responderId);
    const verify = useVerifyCertification(org, responderId);

    async function onVerify(id: string) {
        try {
            await verify.mutateAsync(id);
            toast.success('Certification verified');
        } catch (err) {
            toast.error(err instanceof ApiError ? err.message : 'Verify failed');
        }
    }

    return (
        <Card>
            <CardHeader title={`Certifications${data?.length ? ` · ${data.length}` : ''}`} />
            <div className="p-4">
                {isLoading ? (
                    <SkeletonRows rows={3} />
                ) : !data || data.length === 0 ? (
                    <EmptyState title="No certifications on file" />
                ) : (
                    <ul className="space-y-2">
                        {data.map((c) => (
                            <li
                                key={c.id}
                                className="flex items-center justify-between rounded-md border border-border-subtle bg-surface-2 px-3 py-2"
                            >
                                <div className="flex flex-col">
                                    <span className="text-sm text-content-primary">{c.name}</span>
                                    <span className="text-xs text-content-muted">
                                        {c.authority ?? 'Unverified authority'}
                                        {c.expires_at && ` · expires ${new Date(c.expires_at).toLocaleDateString()}`}
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge tone={certTone[c.status]}>{c.status}</Badge>
                                    {c.status === 'pending' && (
                                        <PermissionGate permission="responders.manage">
                                            <Button size="sm" variant="secondary" loading={verify.isPending} onClick={() => onVerify(c.id)}>
                                                Verify
                                            </Button>
                                        </PermissionGate>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Card>
    );
}
