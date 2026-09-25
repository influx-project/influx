import { Head } from '@inertiajs/react';
import ServiceController from '@/actions/App/Http/Controllers/Admin/ServiceController';
import Heading from '@/components/heading';
import ServiceForm from '@/components/service-form';
import { overview } from '@/routes/admin';
import { create, index } from '@/routes/admin/services';
import type { ServiceOptions, ServiceOwner } from '@/types';

export default function AdminCreateService({
    options,
    owners,
    defaultOwnerId,
}: {
    options: ServiceOptions;
    owners: ServiceOwner[];
    defaultOwnerId: number | null;
}) {
    return (
        <>
            <Head title="Create service" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col p-4">
                <Heading
                    title="Create service"
                    description="Add a service and choose who it is assigned to."
                />
                <ServiceForm
                    options={options}
                    owners={owners}
                    defaultOwnerId={defaultOwnerId}
                    submitRoute={ServiceController.store()}
                    cancelHref={index()}
                />
            </div>
        </>
    );
}

AdminCreateService.layout = {
    breadcrumbs: [
        { title: 'Admin', href: overview() },
        { title: 'Services', href: index() },
        { title: 'Create', href: create() },
    ],
};
