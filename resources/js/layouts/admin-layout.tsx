import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { AdminSidebar } from '@/components/admin-sidebar';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

export default function AdminLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.admin === true;

    // UI-side guard only; the `admin` middleware is the real enforcement.
    useEffect(() => {
        if (!isAdmin) {
            router.visit(dashboard(), { replace: true });
        }
    }, [isAdmin]);

    if (!isAdmin) {
        return null;
    }

    return (
        <AppShell variant="sidebar">
            <AdminSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
