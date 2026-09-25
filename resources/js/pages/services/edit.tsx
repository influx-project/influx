import { Head, setLayoutProps } from '@inertiajs/react';
import ServiceController from '@/actions/App/Http/Controllers/ServiceController';
import Heading from '@/components/heading';
import ServiceForm from '@/components/service-form';
import { edit, index, show } from '@/routes/services';
import type { Service, ServiceOptions } from '@/types';

export default function EditService({
    service,
    options,
}: {
    service: Service;
    options: ServiceOptions;
}) {
    setLayoutProps({
        breadcrumbs: [
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
                    description={`Update how ${service.name} is monitored.`}
                />
                <ServiceForm
                    key={service.id}
                    service={service}
                    options={options}
                    submitRoute={ServiceController.update(service.id)}
                    cancelHref={show(service.id)}
                />
            </div>
        </>
    );
}
