import { Head, setLayoutProps } from '@inertiajs/react';
import ServiceController from '@/actions/App/Http/Controllers/Admin/ServiceController';
import { ServiceDetails } from '@/components/service-details';
import { overview } from '@/routes/admin';
import { edit, index, show } from '@/routes/admin/services';
import { show as showUser } from '@/routes/admin/users';
import type { Service } from '@/types';

export default function AdminShowService({ service }: { service: Service }) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Admin', href: overview() },
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
                ownerHref={showUser}
            />
        </>
    );
}
