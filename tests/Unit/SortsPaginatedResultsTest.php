<?php

declare(strict_types=1);

use App\Http\Controllers\Concerns\SortsPaginatedResults;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

const SORT_MAP = [
    'name' => 'name',
    'status' => 'is_active',
    'monitors_count' => 'monitors_count',
];

function resolveSortFrom(array $query, string $default = 'name', string $defaultDirection = 'asc'): array
{
    app()->instance('request', Request::create('/notifiers', 'GET', $query));

    $sorter = new class
    {
        use SortsPaginatedResults;

        public function resolve(string $default, string $defaultDirection): array
        {
            return $this->resolveSort('sort', 'direction', SORT_MAP, $default, $defaultDirection);
        }
    };

    return $sorter->resolve($default, $defaultDirection);
}

it('maps a whitelisted sort key to its column', function () {
    expect(resolveSortFrom(['sort' => 'status']))->toBe(['is_active', 'asc']);
});

it('falls back to the default column for unknown sort keys', function (string $sort) {
    expect(resolveSortFrom(['sort' => $sort]))->toBe(['name', 'asc']);
})->with(['unknown', 'is_active', '', 'name; drop table notifiers']);

it('uses the default sort and direction when no params are present', function () {
    expect(resolveSortFrom([], 'monitors_count', 'desc'))->toBe(['monitors_count', 'desc']);
});

it('accepts both valid directions', function (string $direction) {
    expect(resolveSortFrom(['direction' => $direction]))->toBe(['name', $direction]);
})->with(['asc', 'desc']);

it('lowercases the direction before validating it', function () {
    expect(resolveSortFrom(['direction' => 'DESC']))->toBe(['name', 'desc']);
});

it('falls back to the default direction for invalid directions', function (string $direction) {
    expect(resolveSortFrom(['direction' => $direction], 'name', 'desc'))->toBe(['name', 'desc']);
})->with(['sideways', '', 'ascending']);

it('is case sensitive for sort keys', function () {
    expect(resolveSortFrom(['sort' => 'STATUS']))->toBe(['name', 'asc']);
});
