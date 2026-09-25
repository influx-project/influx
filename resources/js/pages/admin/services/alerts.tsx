import { Head } from '@inertiajs/react';
import { ServiceAlerts } from '@/components/service-monitoring/alerts';
import ServiceLayout from '@/layouts/service-layout';
import { adminServiceRoutes } from '@/lib/service-routes';
import type { Service, ServiceAlerts as Alerts, ServiceStatus } from '@/types';

export default function AdminServiceAlertsPage({
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
                routes={adminServiceRoutes}
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
