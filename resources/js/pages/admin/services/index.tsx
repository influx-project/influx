import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { ServicesTable } from '@/components/services-table';
import { Button } from '@/components/ui/button';
import { overview } from '@/routes/admin';
import { create, edit, index, show } from '@/routes/admin/services';
import { show as showUser } from '@/routes/admin/users';
import type {
    Paginated,
    Service,
    ServiceFilters,
    ServiceOptions,
    ServiceOwner,
    TableQuery,
} from '@/types';

export default function AdminServicesIndex({
    services,
    query,
    options,
    owners,
}: {
    services: Paginated<Service>;
    query: TableQuery<ServiceFilters>;
    options: ServiceOptions;
    owners: ServiceOwner[];
}) {
    return (
        <>
            <Head title="Services" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Services"
                        description="Every monitored service, across all users."
                    />
                    <Button asChild className="shrink-0">
                        <Link href={create()}>
                            <Plus />
                            New service
                        </Link>
                    </Button>
                </div>

                <ServicesTable
                    services={services}
                    query={query}
                    options={options}
                    route={index}
                    only={['services', 'query']}
                    showHref={show}
                    editHref={edit}
                    owners={owners}
                    ownerHref={showUser}
                />
            </div>
        </>
    );
}

AdminServicesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: overview() },
        { title: 'Services', href: index() },
    ],
};
