import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AdminLayout from '@/layouts/admin-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { configureEcho } from '@laravel/echo-react';

/**
 * Connect to Reverb. Locally the VITE_REVERB_* variables point at the dev server; in
 * production they are left unset, and the WebSocket is served by the same host, port and
 * scheme as the page (the web server proxies /app to Reverb), with the public app key
 * read from the page. That way one build works on any domain, with or without HTTPS.
 */
if (typeof window !== 'undefined') {
    const env = import.meta.env;
    const scheme =
        env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '');
    const port = Number(
        env.VITE_REVERB_PORT ||
            window.location.port ||
            (scheme === 'https' ? 443 : 80),
    );

    configureEcho({
        broadcaster: 'reverb',
        key:
            env.VITE_REVERB_APP_KEY ||
            document
                .querySelector('meta[name="reverb-key"]')
                ?.getAttribute('content') ||
            undefined,
        wsHost: env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('admin/'):
                return AdminLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
