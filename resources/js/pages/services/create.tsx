import { Head } from '@inertiajs/react';
import ServiceController from '@/actions/App/Http/Controllers/ServiceController';
import Heading from '@/components/heading';
import ServiceForm from '@/components/service-form';
import { create, index } from '@/routes/services';
import type { ServiceOptions } from '@/types';

export default function CreateService({
    options,
}: {
    options: ServiceOptions;
}) {
    return (
        <>
            <Head title="Create service" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col p-4">
                <Heading
                    title="Create service"
                    description="Add something new to monitor."
                />
                <ServiceForm
                    options={options}
                    submitRoute={ServiceController.store()}
                    cancelHref={index()}
                />
            </div>
        </>
    );
}

CreateService.layout = {
    breadcrumbs: [
        { title: 'Services', href: index() },
        { title: 'Create', href: create() },
    ],
};
