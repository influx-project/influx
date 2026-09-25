import { Head } from '@inertiajs/react';
import ServiceController from '@/actions/App/Http/Controllers/ServiceController';
import { DaemonConnectionCard } from '@/components/daemon-connection';
import Heading from '@/components/heading';
import ServiceForm from '@/components/service-form';
import { DeleteServiceCard } from '@/components/service-information';
import ServiceLayout from '@/layouts/service-layout';
import { userServiceRoutes } from '@/lib/service-routes';
import type {
    DaemonConnection,
    Service,
    ServiceOptions,
    ServiceStatus,
} from '@/types';

export default function EditService({
    service,
    status,
    daemon_connection,
    options,
}: {
    service: Service;
    status: ServiceStatus;
    daemon_connection: DaemonConnection | null;
    options: ServiceOptions;
}) {
    return (
        <>
            <Head title={`Settings · ${service.name}`} />
            <ServiceLayout
                service={service}
                status={status}
                routes={userServiceRoutes}
                tab="edit"
            >
                <div className="flex max-w-3xl flex-col gap-8">
                    <div className="space-y-6">
                        <Heading
                            variant="small"
                            title="Settings"
                            description={`Update how ${service.name} is monitored.`}
                        />
                        <ServiceForm
                            key={service.id}
                            service={service}
                            options={options}
                            submitRoute={ServiceController.update(service.id)}
                            cancelHref={userServiceRoutes.show(service.id)}
                        />
                    </div>
                    {daemon_connection && (
                        <DaemonConnectionCard
                            connection={daemon_connection}
                            regenerateForm={userServiceRoutes.daemonToken(
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
