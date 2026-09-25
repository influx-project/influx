import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { TableQuery } from '@/types';
import type { RouteDefinition, RouteQueryOptions } from '@/wayfinder';

type TableFilters = { search?: string } & Record<string, string | undefined>;

export type UseTableQueryOptions = {
    /** Builds the URL of the page the table lives on, with the given query string. */
    route: (options?: RouteQueryOptions) => RouteDefinition<'get'>;
    /** The props to reload when the query changes. */
    only: string[];
    defaultSort: string;
    defaultPerPage?: number;
};

/**
 * Drive a server-side filtered, sorted and paginated table through the query
 * string (`filter[...]`, `sort`, `per_page`) that Spatie QueryBuilder reads.
 */
export function useTableQuery<TFilter extends TableFilters>(
    query: TableQuery<TFilter>,
    { route, only, defaultSort, defaultPerPage = 15 }: UseTableQueryOptions,
) {
    const [search, setSearch] = useState(query.filter.search ?? '');
    const [isLoading, setIsLoading] = useState(false);

    const hasActiveFilters = Object.values(query.filter).some(
        (value) => value !== undefined && value !== '',
    );

    const visitOptions = {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only,
        onStart: () => setIsLoading(true),
        onFinish: () => setIsLoading(false),
    };

    /**
     * Apply changes to the query. Any change returns the table to its first page.
     */
    const visit = (
        changes: { filter?: TFilter; sort?: string; per_page?: number },
        { replaceFilter = false }: { replaceFilter?: boolean } = {},
    ) => {
        const filter = Object.fromEntries(
            Object.entries(
                replaceFilter
                    ? { ...changes.filter }
                    : { ...query.filter, ...changes.filter },
            ).filter(([, value]) => value !== undefined && value !== ''),
        );
        const sort = changes.sort ?? query.sort;
        const perPage = changes.per_page ?? query.per_page;

        router.visit(
            route({
                query: {
                    filter,
                    sort: sort === defaultSort ? undefined : sort,
                    per_page: perPage === defaultPerPage ? undefined : perPage,
                },
            }),
            visitOptions,
        );
    };

    useEffect(() => {
        if (search === (query.filter.search ?? '')) {
            return;
        }

        const timeout = setTimeout(
            () =>
                visit({
                    filter: { search: search.trim() || undefined } as TFilter,
                }),
            300,
        );

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const clearFilters = () => {
        setSearch('');
        visit({ filter: {} as TFilter }, { replaceFilter: true });
    };

    return {
        search,
        setSearch,
        isLoading,
        hasActiveFilters,
        visit,
        clearFilters,
        only,
    };
}
