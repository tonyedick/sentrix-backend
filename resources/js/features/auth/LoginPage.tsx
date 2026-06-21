import { useState } from 'react';
import type { FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { api, ApiError } from '@/lib/api';
import { meQueryKey } from '@/auth/AuthProvider';
import type { CurrentUser } from '@/types/api';
import { Logo } from '@/components/ui/Logo';
import { Button } from '@/components/ui/Button';

export function LoginPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    async function onSubmit(e: FormEvent) {
        e.preventDefault();
        setError(null);
        setSubmitting(true);
        try {
            await api.post('/auth/login', { email, password });
            const me = await api.get<CurrentUser>('/auth/me', { with_permissions: true });
            queryClient.setQueryData(meQueryKey, me.data);
            const orgId = me.data.current_organization_id ?? me.data.organizations?.[0]?.id;
            navigate(orgId ? `/${orgId}/dashboard` : '/');
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Unable to sign in.');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-surface-0 px-4">
            <div className="w-full max-w-sm">
                <div className="mb-8 flex flex-col items-center gap-3 text-center">
                    <Logo size={48} />
                    <div>
                        <h1 className="text-lg font-semibold tracking-wide text-content-primary">
                            SENTRI<span className="text-brand-cyan">X</span> Operations
                        </h1>
                        <p className="text-xs text-content-muted">Know the road before it knows you.</p>
                    </div>
                </div>

                <form onSubmit={onSubmit} className="space-y-3 rounded-xl border border-border-subtle bg-surface-1 p-6">
                    {error && (
                        <p className="rounded-md bg-status-danger/15 px-3 py-2 text-xs text-status-danger">{error}</p>
                    )}
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium text-content-secondary">Email</span>
                        <input
                            type="email"
                            required
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            className="w-full rounded-md border border-border-default bg-surface-2 px-3 py-2 text-sm text-content-primary focus:outline-accent"
                            autoComplete="username"
                        />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium text-content-secondary">Password</span>
                        <input
                            type="password"
                            required
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            className="w-full rounded-md border border-border-default bg-surface-2 px-3 py-2 text-sm text-content-primary focus:outline-accent"
                            autoComplete="current-password"
                        />
                    </label>
                    <Button type="submit" variant="primary" size="md" loading={submitting} className="w-full">
                        Sign in
                    </Button>
                </form>
            </div>
        </div>
    );
}
