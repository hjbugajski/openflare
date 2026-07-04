import { useCallback, useEffect, useState } from 'react';

import { router } from '@inertiajs/react';
import {
  type ColumnDef,
  type SortingState,
  getCoreRowModel,
  useReactTable,
} from '@tanstack/react-table';

import { IconChevronLeft } from '@/components/icons/chevron-left';
import { IconChevronRight } from '@/components/icons/chevron-right';
import { Button } from '@/components/ui/button';
import { TableShell } from '@/components/ui/table-shell';
import { formatNumber } from '@/lib/format/number';
import type { CursorPaginated } from '@/types';

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

  const applySortingParams = useCallback(
    (url: URL, nextSorting: SortingState) => {
      const sortEntry = nextSorting[0];

      if (!sortEntry) {
        url.searchParams.delete(sortParam);
        url.searchParams.delete(directionParam);
        return;
      }

      url.searchParams.set(sortParam, sortEntry.id);
      url.searchParams.set(directionParam, sortEntry.desc ? 'desc' : 'asc');
    },
    [sortParam, directionParam],
  );

  const goToCursor = useCallback(
    (cursor: string | null, nextSorting: SortingState = sorting) => {
      const url = new URL(window.location.href);
      applySortingParams(url, nextSorting);

      router.visit(url.pathname + url.search, {
        data: cursor ? { [cursorParam]: cursor } : {},
        preserveState: true,
        preserveScroll: true,
        preserveUrl: true,
        only: reloadOnly,
      });
    },
    [sorting, cursorParam, reloadOnly, applySortingParams],
  );

  const canPreviousPage = Boolean(paginated.prev_cursor);
  const canNextPage = Boolean(paginated.next_cursor);

  const totalPages = Math.max(1, Math.ceil(paginated.total / paginated.per_page));
  const [pageIndex, setPageIndex] = useState(1);

  const handlePreviousPage = useCallback(() => {
    setPageIndex((current) => Math.max(1, current - 1));
    goToCursor(paginated.prev_cursor);
  }, [goToCursor, paginated.prev_cursor]);

  const handleNextPage = useCallback(() => {
    setPageIndex((current) => Math.min(totalPages, current + 1));
    goToCursor(paginated.next_cursor);
  }, [goToCursor, paginated.next_cursor, totalPages]);

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
      <TableShell table={table} columns={columns} />

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
              onClick={handlePreviousPage}
            >
              <span className="sr-only">previous</span>
              <IconChevronLeft />
            </Button>
            <Button variant="tertiary" size="icon" disabled={!canNextPage} onClick={handleNextPage}>
              <span className="sr-only">next</span>
              <IconChevronRight />
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
