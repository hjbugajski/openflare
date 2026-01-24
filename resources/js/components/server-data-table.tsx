import { router } from '@inertiajs/react';
import {
  type ColumnDef,
  type SortingState,
  flexRender,
  getCoreRowModel,
  useReactTable,
} from '@tanstack/react-table';
import { useEffect, useState } from 'react';

import { IconArrowDown } from '@/components/icons/arrow-down';
import { IconArrowUp } from '@/components/icons/arrow-up';
import { IconArrowsSort } from '@/components/icons/arrows-sort';
import { IconChevronLeft } from '@/components/icons/chevron-left';
import { IconChevronRight } from '@/components/icons/chevron-right';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/cn';
import { formatNumber } from '@/lib/format/number';
import type { CursorPaginated } from '@/types';

declare module '@tanstack/react-table' {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  interface ColumnMeta<TData, TValue> {
    className?: string;
  }
}

interface ServerDataTableProps<TData, TValue> {
  columns: ColumnDef<TData, TValue>[];
  paginated: CursorPaginated<TData>;
  cursorParam?: string;
  sortParam?: string;
  directionParam?: string;
  reloadOnly?: string[];
  initialSorting?: SortingState;
}

export function ServerDataTable<TData, TValue>({
  columns,
  paginated,
  cursorParam = 'cursor',
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

  const goToCursor = (cursor: string | null, nextSorting: SortingState = sorting) => {
    const url = new URL(window.location.href);
    applySortingParams(url, nextSorting);

    router.visit(url.pathname + url.search, {
      data: cursor ? { [cursorParam]: cursor } : {},
      preserveState: true,
      preserveScroll: true,
      preserveUrl: true,
      only: reloadOnly,
    });
  };

  const canPreviousPage = Boolean(paginated.prev_cursor);
  const canNextPage = Boolean(paginated.next_cursor);

  const totalPages = Math.max(1, Math.ceil(paginated.total / paginated.per_page));
  const [pageIndex, setPageIndex] = useState(1);

  useEffect(() => {
    if (!paginated.prev_cursor) {
      setPageIndex(1);
      return;
    }

    setPageIndex((current) => Math.min(current, totalPages));
  }, [paginated.prev_cursor, totalPages]);

  const handleSortingChange = (nextSorting: SortingState) => {
    setSorting(nextSorting);
    setPageIndex(1);
    goToCursor(null, nextSorting);
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
    pageCount: -1,
    state: {
      pagination: {
        pageIndex: 0,
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
                      'px-3 py-2 text-left text-xs font-medium whitespace-nowrap text-muted-foreground uppercase',
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

      {paginated.data.length > 0 ? (
        <div className="flex items-center justify-between border-t border-border pt-4 text-sm text-muted-foreground">
          <div>
            page {formatNumber(pageIndex)} of {formatNumber(totalPages)}
          </div>
          <div className="flex gap-2">
            <Button
              variant="tertiary"
              size="icon"
              disabled={!canPreviousPage}
              onClick={() => {
                setPageIndex((current) => Math.max(1, current - 1));
                goToCursor(paginated.prev_cursor);
              }}
            >
              <span className="sr-only">previous</span>
              <IconChevronLeft />
            </Button>
            <Button
              variant="tertiary"
              size="icon"
              disabled={!canNextPage}
              onClick={() => {
                setPageIndex((current) => Math.min(totalPages, current + 1));
                goToCursor(paginated.next_cursor);
              }}
            >
              <span className="sr-only">next</span>
              <IconChevronRight />
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
