import { overview } from '@/routes/admin';
import * as adminServices from '@/routes/admin/services';
import * as services from '@/routes/services';
import type { BreadcrumbItem } from '@/types';
import type { RouteDefinition } from '@/wayfinder';

type ServiceRoute = (id: number) => RouteDefinition<'get'>;

/**
 * The routes for one area's service pages, so tabs can be shared between the user and admin areas.
 */
export type ServiceRoutes = {
    show: ServiceRoute;
    daemon: ServiceRoute;
    downtime: ServiceRoute;
    alerts: ServiceRoute;
    information: ServiceRoute;
    edit: ServiceRoute;
    /** Breadcrumbs leading to the service, not including it. */
    breadcrumbs: BreadcrumbItem[];
};

export const userServiceRoutes: ServiceRoutes = {
    show: services.show,
    daemon: services.daemon,
    downtime: services.downtime,
    alerts: services.alerts,
    information: services.information,
    edit: services.edit,
    breadcrumbs: [{ title: 'Services', href: services.index() }],
};

export const adminServiceRoutes: ServiceRoutes = {
    show: adminServices.show,
    daemon: adminServices.daemon,
    downtime: adminServices.downtime,
    alerts: adminServices.alerts,
    information: adminServices.information,
    edit: adminServices.edit,
    breadcrumbs: [
        { title: 'Admin', href: overview() },
        { title: 'Services', href: adminServices.index() },
    ],
};
