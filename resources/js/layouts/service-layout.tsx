import { Link, setLayoutProps } from '@inertiajs/react';
import { Activity, Bell, Info, LayoutDashboard, Settings } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { ImportanceBadge, ServiceEndpoint } from '@/components/service-badges';
import { ServiceStatusBadge } from '@/components/service-status';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { formatRelative, formatSeconds } from '@/lib/format';
import type { ServiceRoutes } from '@/lib/service-routes';
import { cn } from '@/lib/utils';
import type { Service, ServiceStatus } from '@/types';

type ServiceTab = keyof Pick<
    ServiceRoutes,
    'show' | 'downtime' | 'alerts' | 'information' | 'edit'
>;

const tabs: { key: ServiceTab; title: string; icon: LucideIcon }[] = [
    { key: 'show', title: 'Overview', icon: LayoutDashboard },
    { key: 'downtime', title: 'Downtime', icon: Activity },
    { key: 'alerts', title: 'Alerts', icon: Bell },
    { key: 'information', title: 'Information', icon: Info },
    { key: 'edit', title: 'Settings', icon: Settings },
];

/**
 * The header and tab navigation shared by every page of a service.
 */
export default function ServiceLayout({
    service,
    status,
    routes,
    tab,
    actions,
    children,
}: {
    service: Service;
    status: ServiceStatus;
    routes: ServiceRoutes;
    tab: ServiceTab;
    /** Controls shown on the right of the tab bar, such as a time range picker. */
    actions?: ReactNode;
    children: ReactNode;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const current = tabs.find(({ key }) => key === tab)!;

    setLayoutProps({
        breadcrumbs: [
            ...routes.breadcrumbs,
            { title: service.name, href: routes.show(service.id) },
            ...(tab === 'show'
                ? []
                : [{ title: current.title, href: routes[tab](service.id) }]),
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4">
            <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1.5">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="truncate text-xl font-semibold tracking-tight">
                            {service.name}
                        </h1>
                        <ServiceStatusBadge state={status.state} />
                        <ImportanceBadge service={service} />
                    </div>
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                        <span>{service.type_label}</span>
                        <span aria-hidden>·</span>
                        <ServiceEndpoint service={service} />
                        {service.location && (
                            <>
                                <span aria-hidden>·</span>
                                <span>{service.location}</span>
                            </>
                        )}
                    </div>
                </div>

                <CheckSchedule service={service} status={status} />
            </header>

            <div className="flex flex-col-reverse gap-3 border-b sm:flex-row sm:items-end sm:justify-between">
                <nav
                    className="-mb-px flex gap-1 overflow-x-auto"
                    aria-label={`${service.name} sections`}
                >
                    {tabs.map(({ key, title, icon: Icon }) => {
                        const href = routes[key](service.id);
                        const active = key === tab || isCurrentUrl(href);

                        return (
                            <Link
                                key={key}
                                href={href}
                                preserveScroll
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    active
                                        ? 'border-foreground text-foreground'
                                        : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground',
                                )}
                            >
                                <Icon className="size-4" aria-hidden />
                                {title}
                            </Link>
                        );
                    })}
                </nav>

                {actions && <div className="pb-2 sm:pb-1.5">{actions}</div>}
            </div>

            {children}
        </div>
    );
}

/**
 * When the service was last checked and how often it is checked.
 */
function CheckSchedule({
    service,
    status,
}: {
    service: Service;
    status: ServiceStatus;
}) {
    if (status.state === 'paused' || status.state === 'unsupported') {
        return null;
    }

    return (
        <p className="shrink-0 text-sm text-muted-foreground sm:text-right">
            {status.last_check ? (
                <>
                    Last checked{' '}
                    <time dateTime={status.last_check.checked_at}>
                        {formatRelative(status.last_check.checked_at)}
                    </time>
                </>
            ) : (
                'Not checked yet'
            )}
            <br className="hidden sm:block" />
            <span className="sm:hidden"> · </span>
            Every {formatSeconds(service.check_interval)}
        </p>
    );
}
