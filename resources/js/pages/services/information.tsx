import { Head } from '@inertiajs/react';
import { ServiceInformation } from '@/components/service-information';
import ServiceLayout from '@/layouts/service-layout';
import { userServiceRoutes } from '@/lib/service-routes';
import type { Service, ServiceStatus } from '@/types';

export default function ServiceInformationPage({
    service,
    status,
}: {
    service: Service;
    status: ServiceStatus;
}) {
    return (
        <>
            <Head title={`Information · ${service.name}`} />
            <ServiceLayout
                service={service}
                status={status}
                routes={userServiceRoutes}
                tab="information"
            >
                <div className="max-w-3xl">
                    <ServiceInformation service={service} />
                </div>
            </ServiceLayout>
        </>
    );
}
