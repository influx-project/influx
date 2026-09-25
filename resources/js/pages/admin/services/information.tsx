import { Head } from '@inertiajs/react';
import { ServiceInformation } from '@/components/service-information';
import ServiceLayout from '@/layouts/service-layout';
import { adminServiceRoutes } from '@/lib/service-routes';
import { show as showUser } from '@/routes/admin/users';
import type { Service, ServiceStatus } from '@/types';

export default function AdminServiceInformationPage({
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
                routes={adminServiceRoutes}
                tab="information"
            >
                <div className="max-w-3xl">
                    <ServiceInformation
                        service={service}
                        ownerHref={showUser}
                    />
                </div>
            </ServiceLayout>
        </>
    );
}
