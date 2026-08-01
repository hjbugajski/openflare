<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

trait SortsPaginatedResults
{
    /**
     * Resolve a whitelisted sort column and direction from the request.
     *
     * @param  array<string, string>  $map
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(string $sortParam, string $directionParam, array $map, string $default, string $defaultDirection = 'asc'): array
    {
        $sort = request()->string($sortParam, $default)->toString();
        $sort = $map[$sort] ?? $map[$default];

        $direction = strtolower(request()->string($directionParam, $defaultDirection)->toString());
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : $defaultDirection;

        return [$sort, $direction];
    }

    /**
     * Apply the id tiebreaker and offset-paginate a query that already has its
     * primary `orderBy` applied. Offset pagination keeps every sort stable and
     * page-addressable, including nullable columns and raw expressions that
     * keyset pagination cannot express.
     */
    protected function finalizePage(Builder|Relation $query, string $idColumn, string $direction, string $pageName, int $perPage = 10): LengthAwarePaginator
    {
        return $query
            ->orderBy($idColumn, $direction)
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();
    }
}
