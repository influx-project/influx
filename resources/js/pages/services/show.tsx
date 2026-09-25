import { Head, setLayoutProps } from '@inertiajs/react';
import ServiceController from '@/actions/App/Http/Controllers/ServiceController';
import { ServiceDetails } from '@/components/service-details';
import { edit, index, show } from '@/routes/services';
import type { Service } from '@/types';

export default function ShowService({ service }: { service: Service }) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Services', href: index() },
            { title: service.name, href: show(service.id) },
        ],
    });

    return (
        <>
            <Head title={service.name} />
            <ServiceDetails
                service={service}
                editHref={edit(service.id)}
                destroyForm={ServiceController.destroy.form(service.id)}
            />
        </>
    );
}
