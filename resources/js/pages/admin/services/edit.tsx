import { Head, setLayoutProps } from '@inertiajs/react';
import ServiceController from '@/actions/App/Http/Controllers/Admin/ServiceController';
import Heading from '@/components/heading';
import ServiceForm from '@/components/service-form';
import { overview } from '@/routes/admin';
import { edit, index, show } from '@/routes/admin/services';
import type { Service, ServiceOptions, ServiceOwner } from '@/types';

export default function AdminEditService({
    service,
    options,
    owners,
}: {
    service: Service;
    options: ServiceOptions;
    owners: ServiceOwner[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Admin', href: overview() },
            { title: 'Services', href: index() },
            { title: service.name, href: show(service.id) },
            { title: 'Edit', href: edit(service.id) },
        ],
    });

    return (
        <>
            <Head title={`Edit ${service.name}`} />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col p-4">
                <Heading
                    title="Edit service"
                    description={`Update how ${service.name} is monitored and who owns it.`}
                />
                <ServiceForm
                    key={service.id}
                    service={service}
                    options={options}
                    owners={owners}
                    submitRoute={ServiceController.update(service.id)}
                    cancelHref={show(service.id)}
                />
            </div>
        </>
    );
}
