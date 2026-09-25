<?php

namespace App\Concerns;

use App\Enums\ServiceImportance;
use App\Enums\ServiceType;
use App\Models\Service;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

trait QueriesServices
{
    /**
     * The sort applied when the request does not specify one.
     */
    protected string $defaultServicesSort = '-importance';

    /**
     * The default number of services per page.
     */
    protected int $defaultServicesPerPage = 15;

    /**
     * The maximum number of services a single page may request.
     */
    protected int $maxServicesPerPage = 100;

    /**
     * Get a sorted, filtered and paginated list of services from the request's query string.
     *
     * Supported parameters:
     *  - `filter[search]`: partial match on name, host, location, description or owner
     *  - `filter[type]`: a {@see ServiceType} value
     *  - `filter[importance]`: a {@see ServiceImportance} value
     *  - `filter[enabled]`: `true` / `false`
     *  - `filter[owner]`: a user ID, or `none` for unassigned services (only when not scoped to an owner)
     *  - `sort`: `name`, `host`, `port`, `type`, `importance` or `created_at` (prefix with `-` for descending)
     *  - `per_page`: 1 to 100
     *
     * @return LengthAwarePaginator<int, Service>
     */
    protected function paginatedServices(Request $request, ?User $owner = null): LengthAwarePaginator
    {
        $perPage = min(max($request->integer('per_page', $this->defaultServicesPerPage), 1), $this->maxServicesPerPage);

        $filters = [
            AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                // Filter values are split on commas, so rejoin them to search the literal term.
                $term = is_array($value) ? implode(',', $value) : (string) $value;

                $query->where(fn (Builder $query) => $query
                    ->whereLike('name', "%{$term}%")
                    ->orWhereLike('host', "%{$term}%")
                    ->orWhereLike('location', "%{$term}%")
                    ->orWhereLike('description', "%{$term}%")
                    ->orWhereHas('owner', fn (Builder $query) => $query
                        ->whereLike('name', "%{$term}%")
                        ->orWhereLike('email', "%{$term}%")));
            }),
            AllowedFilter::exact('type'),
            AllowedFilter::exact('importance'),
            AllowedFilter::callback('enabled', fn (Builder $query, mixed $value) => $query
                ->where('enabled', filter_var($value, FILTER_VALIDATE_BOOLEAN))),
        ];

        if ($owner === null) {
            $filters[] = AllowedFilter::callback('owner', fn (Builder $query, mixed $value) => $value === 'none'
                ? $query->whereNull('user_id')
                : $query->where('user_id', (int) $value));
        }

        $query = Service::query()
            ->with('owner')
            ->when($owner !== null, fn (Builder $query) => $query->whereBelongsTo($owner, 'owner'));

        return QueryBuilder::for($query, $request)
            ->allowedFilters(...$filters)
            ->allowedSorts(
                'name',
                'host',
                'port',
                'type',
                AllowedSort::callback('importance', $this->sortByImportance(...)),
                'created_at',
            )
            ->defaultSort($this->defaultServicesSort)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get the current table state to echo back to the UI.
     *
     * @return array{filter: object, sort: string, per_page: int}
     */
    protected function servicesQueryState(Request $request): array
    {
        return [
            'filter' => (object) $request->array('filter'),
            'sort' => $request->string('sort', $this->defaultServicesSort)->toString(),
            'per_page' => $request->integer('per_page', $this->defaultServicesPerPage),
        ];
    }

    /**
     * Get the enum options the services UI needs for filters and forms.
     *
     * @return array{types: list<array{value: string, label: string, uses_port: bool, default_port: int|null, default_ssl_port: int|null}>, importances: list<array{value: string, label: string}>}
     */
    protected function serviceOptions(): array
    {
        return [
            'types' => ServiceType::options(),
            'importances' => ServiceImportance::options(),
        ];
    }

    /**
     * Order by importance rank rather than alphabetically by its stored value.
     *
     * Each level gets its own `importance = ?` clause, from the first level
     * that should be listed to the last, so the SQL stays static and portable.
     *
     * @param  Builder<Service>  $query
     */
    private function sortByImportance(Builder $query, bool $descending): void
    {
        $levels = collect(ServiceImportance::cases())
            ->sortBy(fn (ServiceImportance $importance) => $importance->rank(), descending: $descending);

        foreach ($levels as $importance) {
            $query->orderByRaw('importance = ? DESC', [$importance->value]);
        }

        $query->orderBy('name');
    }
}
