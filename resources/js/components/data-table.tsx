import { Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    Search,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { TableHead } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];
const ALL = 'all';

/**
 * A column header that toggles between ascending and descending sort.
 */
export function SortableTableHead({
    column,
    label,
    sort,
    onSort,
    className,
}: {
    column: string;
    label: string;
    sort: string;
    onSort: (sort: string) => void;
    className?: string;
}) {
    const direction =
        sort === column ? 'asc' : sort === `-${column}` ? 'desc' : null;
    const Icon =
        direction === 'asc'
            ? ArrowUp
            : direction === 'desc'
              ? ArrowDown
              : ChevronsUpDown;

    return (
        <TableHead
            className={className}
            aria-sort={
                direction === 'asc'
                    ? 'ascending'
                    : direction === 'desc'
                      ? 'descending'
                      : 'none'
            }
        >
            <Button
                variant="ghost"
                size="sm"
                className="-ml-3 h-8"
                onClick={() =>
                    onSort(direction === 'asc' ? `-${column}` : column)
                }
            >
                {label}
                <Icon
                    className={cn(
                        'size-3.5',
                        direction === null && 'text-muted-foreground',
                    )}
                    aria-hidden
                />
            </Button>
        </TableHead>
    );
}

/**
 * A search box for a table.
 */
export function DataTableSearch({
    value,
    onChange,
    placeholder,
    label,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    label: string;
}) {
    return (
        <div className="relative lg:max-w-sm lg:flex-1">
            <Search
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden
            />
            <Input
                type="search"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                aria-label={label}
                className="pl-9"
            />
        </div>
    );
}

/**
 * A select that filters a table by one value, or shows everything.
 */
export function DataTableFilter({
    label,
    value,
    options,
    onChange,
    className,
}: {
    label: string;
    value: string | undefined;
    options: { value: string; label: string }[];
    onChange: (value: string | undefined) => void;
    className?: string;
}) {
    return (
        <Select
            value={value ?? ALL}
            onValueChange={(next) => onChange(next === ALL ? undefined : next)}
        >
            <SelectTrigger
                className={cn('w-full lg:w-40', className)}
                aria-label={`Filter by ${label.toLowerCase()}`}
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL}>{label}: All</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/**
 * The search and filter row above a table.
 */
export function DataTableToolbar({
    children,
    hasActiveFilters,
    onClearFilters,
}: {
    children: ReactNode;
    hasActiveFilters: boolean;
    onClearFilters: () => void;
}) {
    return (
        <div className="flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-center">
            {children}
            {hasActiveFilters && (
                <Button
                    variant="ghost"
                    onClick={onClearFilters}
                    className="self-start lg:self-auto"
                >
                    <X />
                    Clear filters
                </Button>
            )}
        </div>
    );
}

/**
 * The bordered table container, dimmed while a new page of results loads.
 */
export function DataTableFrame({
    isLoading,
    children,
}: {
    isLoading: boolean;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'rounded-xl border transition-opacity',
                isLoading && 'opacity-60',
            )}
            aria-busy={isLoading}
        >
            {children}
        </div>
    );
}

/**
 * Result count, rows-per-page picker and previous/next page links.
 */
export function DataTablePagination({
    paginated,
    perPage,
    onPerPageChange,
    only,
}: {
    paginated: Paginated<unknown>;
    perPage: number;
    onPerPageChange: (perPage: number) => void;
    only: string[];
}) {
    const { meta, links } = paginated;

    return (
        <div className="flex flex-col-reverse items-center justify-between gap-4 sm:flex-row">
            <p className="text-sm text-muted-foreground tabular-nums">
                {meta.total === 0
                    ? 'No results'
                    : `Showing ${meta.from}–${meta.to} of ${meta.total}`}
            </p>

            <div className="flex flex-wrap items-center justify-center gap-4">
                <div className="flex items-center gap-2">
                    <span className="text-sm text-muted-foreground">
                        Rows per page
                    </span>
                    <Select
                        value={String(perPage)}
                        onValueChange={(value) =>
                            onPerPageChange(Number(value))
                        }
                    >
                        <SelectTrigger
                            className="w-20"
                            aria-label="Rows per page"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {PER_PAGE_OPTIONS.map((option) => (
                                <SelectItem key={option} value={String(option)}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <span className="text-sm tabular-nums">
                    Page {meta.current_page} of {meta.last_page}
                </span>

                <nav className="flex gap-2" aria-label="Pagination">
                    <PageButton
                        href={links.prev}
                        label="Previous page"
                        only={only}
                    >
                        <ChevronLeft />
                    </PageButton>
                    <PageButton href={links.next} label="Next page" only={only}>
                        <ChevronRight />
                    </PageButton>
                </nav>
            </div>
        </div>
    );
}

function PageButton({
    href,
    label,
    only,
    children,
}: {
    href: string | null;
    label: string;
    only: string[];
    children: ReactNode;
}) {
    if (href === null) {
        return (
            <Button variant="outline" size="icon" disabled aria-label={label}>
                {children}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="icon" asChild>
            <Link
                href={href}
                aria-label={label}
                preserveState
                preserveScroll
                only={only}
            >
                {children}
            </Link>
        </Button>
    );
}
