import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useOrganization, useMembers } from '@/features/organization/api';
import { Card, CardHeader } from '@/components/ui/Card';
import { DataTable } from '@/components/ui/DataTable';
import type { Column } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/StatusPill';
import { EmptyState } from '@/components/ui/EmptyState';
import { cn } from '@/lib/cn';
import type { Member } from '@/types/api';

type Tab = 'organization' | 'members' | 'escalation' | 'notifications';

const TABS: { key: Tab; label: string }[] = [
    { key: 'organization', label: 'Organization' },
    { key: 'members', label: 'Members' },
    { key: 'escalation', label: 'Escalation' },
    { key: 'notifications', label: 'Notifications' },
];

export function SettingsPage() {
    const { org = '' } = useParams();
    const [tab, setTab] = useState<Tab>('organization');

    return (
        <div className="mx-auto max-w-4xl space-y-4 p-4 lg:p-6">
            <h1 className="text-lg font-semibold text-content-primary">Organization Settings</h1>

            <div className="flex gap-1 border-b border-border-subtle">
                {TABS.map((t) => (
                    <button
                        key={t.key}
                        onClick={() => setTab(t.key)}
                        className={cn(
                            'border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                            tab === t.key
                                ? 'border-accent text-content-primary'
                                : 'border-transparent text-content-muted hover:text-content-secondary',
                        )}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {tab === 'organization' && <OrganizationPanel org={org} />}
            {tab === 'members' && <MembersPanel org={org} />}
            {tab === 'escalation' && <ComingSoon title="Escalation policies" />}
            {tab === 'notifications' && <ComingSoon title="Notification policies" />}
        </div>
    );
}

function OrganizationPanel({ org }: { org: string }) {
    const { data, isLoading } = useOrganization(org);
    return (
        <Card>
            <CardHeader title="Profile" />
            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 p-4 text-sm">
                <Field label="Name" value={isLoading ? '…' : data?.name ?? '—'} />
                <Field label="Slug" value={isLoading ? '…' : data?.slug ?? '—'} />
                <Field label="Members" value={isLoading ? '…' : String(data?.members_count ?? '—')} />
                <Field label="Created" value={data?.created_at ? new Date(data.created_at).toLocaleDateString() : '—'} />
            </dl>
        </Card>
    );
}

function MembersPanel({ org }: { org: string }) {
    const { data, isLoading } = useMembers(org);
    const columns: Column<Member>[] = [
        { key: 'name', header: 'Name', className: 'text-content-primary', render: (m) => m.name },
        { key: 'email', header: 'Email', render: (m) => m.email },
        {
            key: 'roles',
            header: 'Roles',
            render: (m) => (
                <div className="flex flex-wrap gap-1">
                    {(m.roles ?? []).map((r) => (
                        <Badge key={r} tone="accent">
                            {r.replace(/[-_]/g, ' ')}
                        </Badge>
                    ))}
                </div>
            ),
        },
    ];
    return (
        <Card>
            <CardHeader title="Members" />
            <DataTable columns={columns} rows={data ?? []} rowKey={(m) => m.id} isLoading={isLoading} emptyTitle="No members" />
        </Card>
    );
}

function Field({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-content-muted">{label}</dt>
            <dd className="mt-0.5 text-content-secondary">{value}</dd>
        </div>
    );
}

function ComingSoon({ title }: { title: string }) {
    return (
        <Card className="p-6">
            <EmptyState title={`${title} — coming soon`} hint="Policy management lands in a later phase. The backend engine is already live." />
        </Card>
    );
}
