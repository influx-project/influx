import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { ServicesTable } from '@/components/services-table';
import { Button } from '@/components/ui/button';
import { create, edit, index, show } from '@/routes/services';
import type {
    Paginated,
    Service,
    ServiceFilters,
    ServiceOptions,
    TableQuery,
} from '@/types';

export default function ServicesIndex({
    services,
    query,
    options,
}: {
    services: Paginated<Service>;
    query: TableQuery<ServiceFilters>;
    options: ServiceOptions;
}) {
    return (
        <>
            <Head title="Services" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Services"
                        description="The servers, ports and endpoints you monitor."
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
                />
            </div>
        </>
    );
}

ServicesIndex.layout = {
    breadcrumbs: [{ title: 'Services', href: index() }],
};
