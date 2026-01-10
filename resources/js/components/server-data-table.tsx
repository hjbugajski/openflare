import { type ChangeEvent, type KeyboardEvent, useEffect, useState } from 'react';

import { router } from '@inertiajs/react';
import {
  type ColumnDef,
  type SortingState,
  flexRender,
  getCoreRowModel,
  useReactTable,
} from '@tanstack/react-table';

import { IconArrowDown } from '@/components/icons/arrow-down';
import { IconArrowUp } from '@/components/icons/arrow-up';
import { IconArrowsSort } from '@/components/icons/arrows-sort';
import { IconChevronDoubleLeft } from '@/components/icons/chevron-double-left';
import { IconChevronDoubleRight } from '@/components/icons/chevron-double-right';
import { IconChevronLeft } from '@/components/icons/chevron-left';
import { IconChevronRight } from '@/components/icons/chevron-right';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/cn';
import type { Paginated } from '@/types';

declare module '@tanstack/react-table' {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  interface ColumnMeta<TData, TValue> {
    className?: string;
  }
}

interface ServerDataTableProps<TData, TValue> {
  columns: ColumnDef<TData, TValue>[];
  paginated: Paginated<TData>;
  queryParam?: string;
  sortParam?: string;
  directionParam?: string;
  reloadOnly?: string[];
  initialSorting?: SortingState;
}

export function ServerDataTable<TData, TValue>({
  columns,
  paginated,
  queryParam = 'page',
  sortParam = 'sort',
  directionParam = 'direction',
  reloadOnly,
  initialSorting = [],
}: ServerDataTableProps<TData, TValue>) {
  const [sorting, setSorting] = useState<SortingState>(() => {
    if (typeof window === 'undefined') {
      return initialSorting;
    }

    const url = new URL(window.location.href);
    const sort = url.searchParams.get(sortParam);

    if (!sort) {
      return initialSorting;
    }

    return [{ id: sort, desc: url.searchParams.get(directionParam) === 'desc' }];
  });

  const applySortingParams = (url: URL, nextSorting: SortingState) => {
    const sortEntry = nextSorting[0];

    if (!sortEntry) {
      url.searchParams.delete(sortParam);
      url.searchParams.delete(directionParam);
      return;
    }

    url.searchParams.set(sortParam, sortEntry.id);
    url.searchParams.set(directionParam, sortEntry.desc ? 'desc' : 'asc');
  };

  const goToPage = (page: number, nextSorting: SortingState = sorting) => {
    const url = new URL(window.location.href);
    url.searchParams.set(queryParam, String(page));
    applySortingParams(url, nextSorting);

    router.visit(url.pathname + url.search, {
      preserveState: true,
      preserveScroll: true,
      only: reloadOnly,
    });
  };

  const canPreviousPage = paginated.current_page > 1;
  const canNextPage = paginated.current_page < paginated.last_page;

  const [pageInput, setPageInput] = useState(String(paginated.current_page));

  useEffect(() => {
    setPageInput(String(paginated.current_page));
  }, [paginated.current_page]);

  const commitPageInput = (value: string) => {
    const trimmedValue = value.trim();

    if (trimmedValue === '') {
      setPageInput(String(paginated.current_page));
      return;
    }

    const nextPage = Number(trimmedValue);

    if (Number.isNaN(nextPage)) {
      setPageInput(String(paginated.current_page));
      return;
    }

    const clampedPage = Math.min(Math.max(nextPage, 1), paginated.last_page);
    setPageInput(String(clampedPage));

    if (clampedPage !== paginated.current_page) {
      goToPage(clampedPage);
    }
  };

  const handlePageInputChange = (event: ChangeEvent<HTMLInputElement>) => {
    setPageInput(event.target.value);
  };

  const handleSortingChange = (nextSorting: SortingState) => {
    setSorting(nextSorting);
    goToPage(1, nextSorting);
  };

  const handlePageInputBlur = () => {
    commitPageInput(pageInput);
  };

  const handlePageInputKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      commitPageInput(pageInput);
    }
  };

  const handleSortingChangeWrapper = (
    nextSorting: SortingState | ((prev: SortingState) => SortingState),
  ) => {
    const resolvedSorting = typeof nextSorting === 'function' ? nextSorting(sorting) : nextSorting;

    handleSortingChange(resolvedSorting);
  };

  // eslint-disable-next-line react-hooks/incompatible-library -- compiler auto-skips, acknowledged
  const table = useReactTable({
    data: paginated.data,
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    manualSorting: true,
    pageCount: paginated.last_page,
    state: {
      pagination: {
        pageIndex: paginated.current_page - 1,
        pageSize: paginated.per_page,
      },
      sorting,
    },
    onSortingChange: handleSortingChangeWrapper,
  });

  return (
    <div className="space-y-4">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            {table.getHeaderGroups().map((headerGroup) => (
              <tr key={headerGroup.id} className="border-b border-border">
                {headerGroup.headers.map((header) => (
                  <th
                    key={header.id}
                    className={cn(
                      'px-3 py-2 text-left text-xs font-medium text-muted-foreground uppercase',
                      header.column.columnDef.meta?.className,
                    )}
                  >
                    {header.isPlaceholder ? null : header.column.getCanSort() ? (
                      <button
                        type="button"
                        className="inline-flex items-center gap-1 transition-colors hover:text-foreground"
                        onClick={header.column.getToggleSortingHandler()}
                      >
                        <span>
                          {flexRender(header.column.columnDef.header, header.getContext())}
                        </span>
                        <span aria-hidden className="text-muted-foreground">
                          {header.column.getIsSorted() === 'asc' ? (
                            <IconArrowUp className="size-3" />
                          ) : header.column.getIsSorted() === 'desc' ? (
                            <IconArrowDown className="size-3" />
                          ) : (
                            <IconArrowsSort className="size-3" />
                          )}
                        </span>
                      </button>
                    ) : (
                      flexRender(header.column.columnDef.header, header.getContext())
                    )}
                  </th>
                ))}
              </tr>
            ))}
          </thead>
          <tbody>
            {table.getRowModel().rows?.length ? (
              table.getRowModel().rows.map((row) => (
                <tr key={row.id} className="border-b border-border last:border-0">
                  {row.getVisibleCells().map((cell) => (
                    <td
                      key={cell.id}
                      className={cn('px-3 py-2', cell.column.columnDef.meta?.className)}
                    >
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </td>
                  ))}
                </tr>
              ))
            ) : (
              <tr>
                <td
                  colSpan={columns.length}
                  className="px-3 py-8 text-center text-muted-foreground"
                >
                  <span aria-hidden className="text-accent">
                    [
                  </span>
                  <span className="mx-1">no results</span>
                  <span aria-hidden className="text-accent">
                    ]
                  </span>
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {paginated.last_page > 1 ? (
        <div className="flex items-center justify-between border-t border-border pt-4">
          <div className="flex items-center gap-3 text-sm text-muted-foreground">
            <span>page</span>
            <Input
              inputSize="sm"
              type="text"
              inputMode="numeric"
              pattern="[0-9]*"
              value={pageInput}
              className="w-12 text-center"
              aria-label="page number"
              onChange={handlePageInputChange}
              onBlur={handlePageInputBlur}
              onKeyDown={handlePageInputKeyDown}
            />
            <span>of {paginated.last_page}</span>
          </div>

          <div className="flex gap-2">
            <Button
              variant="tertiary"
              size="icon"
              disabled={!canPreviousPage}
              onClick={() => goToPage(1)}
            >
              <span className="sr-only">first</span>
              <IconChevronDoubleLeft />
            </Button>
            <Button
              variant="tertiary"
              size="icon"
              disabled={!canPreviousPage}
              onClick={() => goToPage(paginated.current_page - 1)}
            >
              <span className="sr-only">previous</span>
              <IconChevronLeft />
            </Button>
            <Button
              variant="tertiary"
              size="icon"
              disabled={!canNextPage}
              onClick={() => goToPage(paginated.current_page + 1)}
            >
              <span className="sr-only">next</span>
              <IconChevronRight />
            </Button>
            <Button
              variant="tertiary"
              size="icon"
              disabled={!canNextPage}
              onClick={() => goToPage(paginated.last_page)}
            >
              <span className="sr-only">last</span>
              <IconChevronDoubleRight />
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
