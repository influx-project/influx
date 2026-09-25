import { Head } from '@inertiajs/react';
import ServiceController from '@/actions/App/Http/Controllers/Admin/ServiceController';
import { DaemonConnectionCard } from '@/components/daemon-connection';
import Heading from '@/components/heading';
import ServiceForm from '@/components/service-form';
import { DeleteServiceCard } from '@/components/service-information';
import ServiceLayout from '@/layouts/service-layout';
import { adminServiceRoutes } from '@/lib/service-routes';
import type {
    DaemonConnection,
    Service,
    ServiceOptions,
    ServiceOwner,
    ServiceStatus,
} from '@/types';

export default function AdminEditService({
    service,
    status,
    daemon_connection,
    options,
    owners,
}: {
    service: Service;
    status: ServiceStatus;
    daemon_connection: DaemonConnection | null;
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
                    {daemon_connection && (
                        <DaemonConnectionCard
                            connection={daemon_connection}
                            regenerateForm={adminServiceRoutes.daemonToken(
                                service.id,
                            )}
                        />
                    )}
                    <DeleteServiceCard
                        service={service}
                        destroyForm={ServiceController.destroy.form(service.id)}
                    />
                </div>
            </ServiceLayout>
        </>
    );
}
