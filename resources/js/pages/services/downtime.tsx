import { Head } from '@inertiajs/react';
import { ServiceDowntime } from '@/components/service-monitoring/downtime';
import ServiceLayout from '@/layouts/service-layout';
import { userServiceRoutes } from '@/lib/service-routes';
import type {
    Incident,
    Paginated,
    Service,
    ServiceDowntime as Downtime,
    ServiceStatus,
} from '@/types';

export default function ServiceDowntimePage({
    service,
    status,
    downtime,
    incidents,
}: {
    service: Service;
    status: ServiceStatus;
    downtime: Downtime;
    incidents: Paginated<Incident>;
}) {
    return (
        <>
            <Head title={`Downtime · ${service.name}`} />
            <ServiceLayout
                service={service}
                status={status}
                routes={userServiceRoutes}
                tab="downtime"
            >
                <ServiceDowntime
                    service={service}
                    status={status}
                    downtime={downtime}
                    incidents={incidents}
                />
            </ServiceLayout>
        </>
    );
}
