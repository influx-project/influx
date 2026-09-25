import { Link } from '@inertiajs/react';
import { Activity, Eye, MoreHorizontal, Pencil, Radio } from 'lucide-react';
import {
    DataTableFilter,
    DataTableFrame,
    DataTablePagination,
    DataTableSearch,
    DataTableToolbar,
    SortableTableHead,
} from '@/components/data-table';
import {
    ImportanceBadge,
    MonitoringBadge,
    ServiceEndpoint,
} from '@/components/service-badges';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTableQuery } from '@/hooks/use-table-query';
import type {
    Paginated,
    Service,
    ServiceFilters,
    ServiceOptions,
    ServiceOwner,
    TableQuery,
} from '@/types';
import type { RouteDefinition, RouteQueryOptions } from '@/wayfinder';

export const DEFAULT_SERVICES_SORT = '-importance';

type RouteForId = (id: number) => RouteDefinition<'get'>;

export type ServicesTableProps = {
    services: Paginated<Service>;
    query: TableQuery<ServiceFilters>;
    options: ServiceOptions;
    /** Builds the URL of the page the table is on, with the given query string. */
    route: (options?: RouteQueryOptions) => RouteDefinition<'get'>;
    /** The page props holding the services and their query, reloaded on change. */
    only: [services: string, query: string];
    showHref: RouteForId;
    editHref: RouteForId;
    /** When given, an owner column and owner filter are shown. */
    owners?: ServiceOwner[];
    ownerHref?: RouteForId;
};

/**
 * A searchable, filterable, sortable and paginated table of services.
 */
export function ServicesTable({
    services,
    query,
    options,
    route,
    only,
    showHref,
    editHref,
    owners,
    ownerHref,
}: ServicesTableProps) {
    const table = useTableQuery(query, {
        route,
        only,
        defaultSort: DEFAULT_SERVICES_SORT,
    });

    const onSort = (sort: string) => table.visit({ sort });
    const showOwner = owners !== undefined;
    const columnCount = showOwner ? 7 : 6;

    return (
        <div className="flex flex-col gap-4">
            <DataTableToolbar
                hasActiveFilters={table.hasActiveFilters}
                onClearFilters={table.clearFilters}
            >
                <DataTableSearch
                    value={table.search}
                    onChange={table.setSearch}
                    placeholder={
                        showOwner
                            ? 'Search name, host, location or owner'
                            : 'Search name, host or location'
                    }
                    label="Search services"
                />
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:flex">
                    <DataTableFilter
                        label="Type"
                        value={query.filter.type}
                        options={options.types}
                        onChange={(type) =>
                            table.visit({
                                filter: {
                                    type: type as ServiceFilters['type'],
                                },
                            })
                        }
                    />
                    <DataTableFilter
                        label="Importance"
                        value={query.filter.importance}
                        options={options.importances}
                        onChange={(importance) =>
                            table.visit({
                                filter: {
                                    importance:
                                        importance as ServiceFilters['importance'],
                                },
                            })
                        }
                    />
                    <DataTableFilter
                        label="Status"
                        value={query.filter.enabled}
                        options={[
                            { value: 'true', label: 'Active' },
                            { value: 'false', label: 'Paused' },
                        ]}
                        onChange={(enabled) =>
                            table.visit({
                                filter: {
                                    enabled:
                                        enabled as ServiceFilters['enabled'],
                                },
                            })
                        }
                    />
                    {showOwner && (
                        <DataTableFilter
                            label="Owner"
                            value={query.filter.owner}
                            options={[
                                { value: 'none', label: 'Unassigned' },
                                ...owners.map((owner) => ({
                                    value: String(owner.id),
                                    label: owner.name,
                                })),
                            ]}
                            onChange={(owner) =>
                                table.visit({ filter: { owner } })
                            }
                        />
                    )}
                </div>
            </DataTableToolbar>

            <DataTableFrame isLoading={table.isLoading}>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <SortableTableHead
                                column="name"
                                label="Name"
                                sort={query.sort}
                                onSort={onSort}
                                className="pl-4"
                            />
                            <SortableTableHead
                                column="host"
                                label="Endpoint"
                                sort={query.sort}
                                onSort={onSort}
                                className="hidden md:table-cell"
                            />
                            <SortableTableHead
                                column="type"
                                label="Type"
                                sort={query.sort}
                                onSort={onSort}
                                className="hidden lg:table-cell"
                            />
                            <SortableTableHead
                                column="importance"
                                label="Importance"
                                sort={query.sort}
                                onSort={onSort}
                            />
                            <TableHead className="hidden sm:table-cell">
                                Monitoring
                            </TableHead>
                            {showOwner && (
                                <TableHead className="hidden xl:table-cell">
                                    Owner
                                </TableHead>
                            )}
                            <TableHead className="w-12 pr-4">
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {services.data.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={columnCount}
                                    className="h-32 text-center text-muted-foreground"
                                >
                                    {table.hasActiveFilters
                                        ? 'No services match your search or filters.'
                                        : 'No services yet.'}
                                </TableCell>
                            </TableRow>
                        ) : (
                            services.data.map((service) => (
                                <TableRow key={service.id}>
                                    <TableCell className="max-w-64 pl-4">
                                        <Link
                                            href={showHref(service.id)}
                                            className="block truncate rounded-md font-medium hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                        >
                                            {service.name}
                                        </Link>
                                        {service.location && (
                                            <p className="truncate text-xs text-muted-foreground">
                                                {service.location}
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell className="hidden max-w-56 md:table-cell">
                                        <ServiceEndpoint service={service} />
                                    </TableCell>
                                    <TableCell className="hidden text-muted-foreground lg:table-cell">
                                        {service.type_label}
                                    </TableCell>
                                    <TableCell>
                                        <ImportanceBadge service={service} />
                                    </TableCell>
                                    <TableCell className="hidden sm:table-cell">
                                        <div className="flex items-center gap-2">
                                            <MonitoringBadge
                                                enabled={service.enabled}
                                            />
                                            <MetricsIndicators
                                                service={service}
                                            />
                                        </div>
                                    </TableCell>
                                    {showOwner && (
                                        <TableCell className="hidden max-w-48 truncate xl:table-cell">
                                            {service.owner ? (
                                                ownerHref ? (
                                                    <Link
                                                        href={ownerHref(
                                                            service.owner.id,
                                                        )}
                                                        className="hover:underline"
                                                    >
                                                        {service.owner.name}
                                                    </Link>
                                                ) : (
                                                    service.owner.name
                                                )
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    Unassigned
                                                </span>
                                            )}
                                        </TableCell>
                                    )}
                                    <TableCell className="pr-4 text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Actions for ${service.name}`}
                                                >
                                                    <MoreHorizontal />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={showHref(
                                                            service.id,
                                                        )}
                                                    >
                                                        <Eye />
                                                        View
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={editHref(
                                                            service.id,
                                                        )}
                                                    >
                                                        <Pencil />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </DataTableFrame>

            <DataTablePagination
                paginated={services}
                perPage={query.per_page}
                onPerPageChange={(per_page) => table.visit({ per_page })}
                only={table.only}
            />
        </div>
    );
}

/**
 * Icons showing which kinds of metrics are enabled for a service.
 */
function MetricsIndicators({ service }: { service: Service }) {
    const indicators = [
        {
            enabled: service.collect_metrics,
            icon: Activity,
            label: 'Background metrics collection',
        },
        {
            enabled: service.stream_metrics,
            icon: Radio,
            label: 'Live metrics over websocket',
        },
    ].filter((indicator) => indicator.enabled);

    return indicators.map(({ icon: Icon, label }) => (
        <Tooltip key={label}>
            <TooltipTrigger asChild>
                <span className="text-muted-foreground" tabIndex={0}>
                    <Icon className="size-4" aria-hidden />
                    <span className="sr-only">{label}</span>
                </span>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    ));
}
