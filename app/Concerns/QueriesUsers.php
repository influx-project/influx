<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

trait QueriesUsers
{
    /**
     * The default number of users per page.
     */
    protected int $defaultUsersPerPage = 15;

    /**
     * The maximum number of users a single page may request.
     */
    protected int $maxUsersPerPage = 100;

    /**
     * Get a sorted, filtered and paginated list of users from the request's query string.
     *
     * Supported parameters:
     *  - `filter[search]`: partial match on name or email
     *  - `filter[admin]`: `true` / `false`
     *  - `filter[verified]`: `true` / `false`
     *  - `filter[two_factor]`: `true` / `false`
     *  - `sort`: `name`, `email`, `created_at` or `email_verified_at` (prefix with `-` for descending)
     *  - `per_page`: 1 to 100
     *
     * @return LengthAwarePaginator<int, User>
     */
    protected function paginatedUsers(Request $request): LengthAwarePaginator
    {
        $perPage = min(max($request->integer('per_page', $this->defaultUsersPerPage), 1), $this->maxUsersPerPage);

        return QueryBuilder::for(User::class, $request)
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    // Filter values are split on commas, so rejoin them to search the literal term.
                    $term = is_array($value) ? implode(',', $value) : (string) $value;

                    $query->where(fn (Builder $query) => $query
                        ->whereLike('name', "%{$term}%")
                        ->orWhereLike('email', "%{$term}%"));
                }),
                AllowedFilter::callback('admin', fn (Builder $query, mixed $value) => $query
                    ->where('admin', $this->filterBoolean($value))),
                AllowedFilter::callback('verified', fn (Builder $query, mixed $value) => $this->filterBoolean($value)
                    ? $query->whereNotNull('email_verified_at')
                    : $query->whereNull('email_verified_at')),
                AllowedFilter::callback('two_factor', fn (Builder $query, mixed $value) => $this->filterBoolean($value)
                    ? $query->whereNotNull('two_factor_confirmed_at')
                    : $query->whereNull('two_factor_confirmed_at')),
            )
            ->allowedSorts('name', 'email', 'created_at', 'email_verified_at')
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Interpret a boolean-like filter value such as `true`, `1` or `0`.
     */
    private function filterBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
