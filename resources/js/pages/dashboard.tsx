import { Head } from '@inertiajs/react';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { dashboard } from '@/routes';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div
                        className="relative aspect-video glass-lift overflow-hidden rounded-xl glass motion-safe:animate-rise"
                        style={{ animationDelay: '0ms' }}
                    >
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-primary/20" />
                    </div>
                    <div
                        className="relative aspect-video glass-lift overflow-hidden rounded-xl glass motion-safe:animate-rise"
                        style={{ animationDelay: '90ms' }}
                    >
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-primary/20" />
                    </div>
                    <div
                        className="relative aspect-video glass-lift overflow-hidden rounded-xl glass motion-safe:animate-rise"
                        style={{ animationDelay: '180ms' }}
                    >
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-primary/20" />
                    </div>
                </div>
                <div
                    className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl glass motion-safe:animate-rise md:min-h-min"
                    style={{ animationDelay: '300ms' }}
                >
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-primary/20" />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
