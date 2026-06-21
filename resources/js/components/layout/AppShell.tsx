import { Outlet } from 'react-router-dom';
import { TopBar } from '@/components/layout/TopBar';
import { LeftRail } from '@/components/layout/LeftRail';
import { ConnectionBanner } from '@/components/layout/ConnectionBanner';
import { CommandPalette } from '@/components/layout/CommandPalette';

/** Authenticated application chrome: top bar, left rail, live content area. */
export function AppShell() {
    return (
        <div className="flex h-full min-h-screen flex-col bg-surface-0">
            <TopBar />
            <ConnectionBanner />
            <div className="flex flex-1 overflow-hidden">
                <LeftRail />
                <main className="flex-1 overflow-y-auto">
                    <Outlet />
                </main>
            </div>
            <CommandPalette />
        </div>
    );
}
