import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from '@/auth/AuthProvider';
import { RequireAuth, RequireOrgAccess, RequirePermission } from '@/auth/guards';
import { FullPageSpinner } from '@/components/ui/Spinner';
import { AppShell } from '@/components/layout/AppShell';
import { LoginPage } from '@/features/auth/LoginPage';
import { DashboardPage } from '@/features/dashboard/DashboardPage';
import { IncidentListPage } from '@/features/incidents/IncidentListPage';
import { IncidentDetailPage } from '@/features/incidents/IncidentDetailPage';
import { ResponderListPage } from '@/features/responders/ResponderListPage';
import { ResponderDetailPage } from '@/features/responders/ResponderDetailPage';
import { EmergencyListPage } from '@/features/emergencies/EmergencyListPage';
import { EmergencyDetailPage } from '@/features/emergencies/EmergencyDetailPage';
import { EscalationsPage } from '@/features/escalations/EscalationsPage';
import { NotificationCenterPage } from '@/features/notifications/NotificationCenterPage';
import { SettingsPage } from '@/features/organization/SettingsPage';

/** Sends the user to their active org dashboard, or to login. */
function RootRedirect() {
    const { user, isLoading, activeOrgId } = useAuth();
    if (isLoading) return <FullPageSpinner />;
    if (!user) return <Navigate to="/login" replace />;
    if (activeOrgId) return <Navigate to={`/${activeOrgId}/dashboard`} replace />;
    return <Navigate to="/login" replace />;
}

export function AppRouter() {
    return (
        <BrowserRouter>
            <Routes>
                <Route path="/login" element={<LoginPage />} />
                <Route path="/" element={<RootRedirect />} />

                <Route
                    path="/:org"
                    element={
                        <RequireAuth>
                            <RequireOrgAccess>
                                <AppShell />
                            </RequireOrgAccess>
                        </RequireAuth>
                    }
                >
                    <Route index element={<Navigate to="dashboard" replace />} />
                    <Route
                        path="dashboard"
                        element={
                            <RequirePermission permission="incidents.view">
                                <DashboardPage />
                            </RequirePermission>
                        }
                    />
                    <Route
                        path="incidents"
                        element={
                            <RequirePermission permission="incidents.view">
                                <IncidentListPage />
                            </RequirePermission>
                        }
                    />
                    <Route
                        path="incidents/:incidentId"
                        element={
                            <RequirePermission permission="incidents.view">
                                <IncidentDetailPage />
                            </RequirePermission>
                        }
                    />
                    <Route
                        path="responders"
                        element={
                            <RequirePermission permission="responders.view">
                                <ResponderListPage />
                            </RequirePermission>
                        }
                    />
                    <Route
                        path="responders/:responderId"
                        element={
                            <RequirePermission permission="responders.view">
                                <ResponderDetailPage />
                            </RequirePermission>
                        }
                    />
                    <Route
                        path="emergencies"
                        element={
                            <RequirePermission permission="emergencies.view">
                                <EmergencyListPage />
                            </RequirePermission>
                        }
                    />
                    <Route
                        path="emergencies/:emergencyId"
                        element={
                            <RequirePermission permission="emergencies.view">
                                <EmergencyDetailPage />
                            </RequirePermission>
                        }
                    />
                    <Route
                        path="escalations"
                        element={
                            <RequirePermission permission="incidents.view">
                                <EscalationsPage />
                            </RequirePermission>
                        }
                    />
                    <Route path="notifications" element={<NotificationCenterPage />} />
                    <Route path="settings" element={<SettingsPage />} />
                </Route>

                <Route path="*" element={<RootRedirect />} />
            </Routes>
        </BrowserRouter>
    );
}
