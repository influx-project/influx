import { Head } from '@inertiajs/react';
import { ServiceAlerts } from '@/components/service-monitoring/alerts';
import ServiceLayout from '@/layouts/service-layout';
import { userServiceRoutes } from '@/lib/service-routes';
import type { Service, ServiceAlerts as Alerts, ServiceStatus } from '@/types';

export default function ServiceAlertsPage({
    service,
    status,
    alerts,
}: {
    service: Service;
    status: ServiceStatus;
    alerts: Alerts;
}) {
    return (
        <>
            <Head title={`Alerts · ${service.name}`} />
            <ServiceLayout
                service={service}
                status={status}
                routes={userServiceRoutes}
                tab="alerts"
            >
                <ServiceAlerts
                    service={service}
                    status={status}
                    alerts={alerts}
                />
            </ServiceLayout>
        </>
    );
}
