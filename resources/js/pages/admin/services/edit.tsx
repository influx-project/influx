import { Head } from '@inertiajs/react';
import ServiceController from '@/actions/App/Http/Controllers/Admin/ServiceController';
import Heading from '@/components/heading';
import ServiceForm from '@/components/service-form';
import { DeleteServiceCard } from '@/components/service-information';
import ServiceLayout from '@/layouts/service-layout';
import { adminServiceRoutes } from '@/lib/service-routes';
import type {
    Service,
    ServiceOptions,
    ServiceOwner,
    ServiceStatus,
} from '@/types';

export default function AdminEditService({
    service,
    status,
    options,
    owners,
}: {
    service: Service;
    status: ServiceStatus;
    options: ServiceOptions;
    owners: ServiceOwner[];
}) {
    return (
        <>
            <Head title={`Settings · ${service.name}`} />
            <ServiceLayout
                service={service}
                status={status}
                routes={adminServiceRoutes}
                tab="edit"
            >
                <div className="flex max-w-3xl flex-col gap-8">
                    <div className="space-y-6">
                        <Heading
                            variant="small"
                            title="Settings"
                            description={`Update how ${service.name} is monitored and who owns it.`}
                        />
                        <ServiceForm
                            key={service.id}
                            service={service}
                            options={options}
                            owners={owners}
                            submitRoute={ServiceController.update(service.id)}
                            cancelHref={adminServiceRoutes.show(service.id)}
                        />
                    </div>
                    <DeleteServiceCard
                        service={service}
                        destroyForm={ServiceController.destroy.form(service.id)}
                    />
                </div>
            </ServiceLayout>
        </>
    );
}
