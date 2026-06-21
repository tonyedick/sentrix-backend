import { useState } from 'react';
import type { FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { useOpenIncident } from '@/features/incidents/api';
import { ApiError } from '@/lib/api';
import { SlideOver } from '@/components/ui/SlideOver';
import { Button } from '@/components/ui/Button';
import { toast } from '@/components/ui/Toast';
import type { IncidentSeverity } from '@/types/api';

const SEVERITIES: IncidentSeverity[] = ['low', 'medium', 'high', 'critical'];

export function NewIncidentSlideOver({ org, open, onClose }: { org: string; open: boolean; onClose: () => void }) {
    const navigate = useNavigate();
    const openIncident = useOpenIncident(org);
    const [title, setTitle] = useState('');
    const [severity, setSeverity] = useState<IncidentSeverity>('medium');
    const [summary, setSummary] = useState('');
    const [error, setError] = useState<string | null>(null);

    async function submit(e: FormEvent) {
        e.preventDefault();
        setError(null);
        try {
            const incident = await openIncident.mutateAsync({ title, severity, summary: summary || undefined });
            toast.success('Incident opened');
            onClose();
            setTitle('');
            setSummary('');
            navigate(`/${org}/incidents/${incident.id}`);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Could not open incident.');
        }
    }

    return (
        <SlideOver open={open} onClose={onClose} title="Open incident">
            <form onSubmit={submit} className="space-y-4">
                {error && <p className="rounded-md bg-status-danger/15 px-3 py-2 text-xs text-status-danger">{error}</p>}
                <label className="block">
                    <span className="mb-1 block text-xs font-medium text-content-secondary">Title</span>
                    <input
                        required
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        className="w-full rounded-md border border-border-default bg-surface-2 px-3 py-2 text-sm text-content-primary focus:outline-accent"
                        placeholder="e.g. Vehicle collision on Route 9"
                    />
                </label>
                <label className="block">
                    <span className="mb-1 block text-xs font-medium text-content-secondary">Severity</span>
                    <select
                        value={severity}
                        onChange={(e) => setSeverity(e.target.value as IncidentSeverity)}
                        className="w-full rounded-md border border-border-default bg-surface-2 px-3 py-2 text-sm capitalize text-content-primary focus:outline-accent"
                    >
                        {SEVERITIES.map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="block">
                    <span className="mb-1 block text-xs font-medium text-content-secondary">Summary (optional)</span>
                    <textarea
                        value={summary}
                        onChange={(e) => setSummary(e.target.value)}
                        rows={4}
                        className="w-full rounded-md border border-border-default bg-surface-2 px-3 py-2 text-sm text-content-primary focus:outline-accent"
                    />
                </label>
                <div className="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" variant="primary" loading={openIncident.isPending}>
                        Open incident
                    </Button>
                </div>
            </form>
        </SlideOver>
    );
}
