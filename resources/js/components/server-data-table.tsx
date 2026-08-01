import { useCallback, useState } from 'react';

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
import type { Paginated } from '@/types';

interface ServerDataTableProps<TData, TValue> {
  columns: ColumnDef<TData, TValue>[];
  paginated: Paginated<TData>;
  /*
   * Param names are required so every table states the contract its server
   * controller reads — a defaulted name that disagrees with the backend
   * makes pagination a silent no-op.
   */
  pageParam: string;
  sortParam: string;
  directionParam: string;
  reloadOnly?: string[];
  initialSorting?: SortingState;
}

export function ServerDataTable<TData, TValue>({
  columns,
  paginated,
  pageParam,
  sortParam,
  directionParam,
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

  /*
   * Page and sort live in the real URL, never in `preserveUrl` request data:
   * a debounced `router.reload()` elsewhere on the page resolves against
   * window.location, so state kept out of the URL silently resets the table.
   * Each table only ever writes its own params, so sibling tables keep theirs.
   */
  const goToPage = useCallback(
    (page: number, nextSorting: SortingState = sorting) => {
      const url = new URL(window.location.href);
      applySortingParams(url, nextSorting);

      if (page > 1) {
        url.searchParams.set(pageParam, String(page));
      } else {
        url.searchParams.delete(pageParam);
      }

      router.visit(url.pathname + url.search, {
        preserveState: true,
        preserveScroll: true,
        only: reloadOnly,
      });
    },
    [sorting, pageParam, reloadOnly, applySortingParams],
  );

  const currentPage = paginated.current_page;
  const lastPage = Math.max(1, paginated.last_page);
  const canPreviousPage = currentPage > 1;
  const canNextPage = currentPage < lastPage;

  const handlePreviousPage = useCallback(() => {
    goToPage(currentPage - 1);
  }, [goToPage, currentPage]);

  const handleNextPage = useCallback(() => {
    goToPage(currentPage + 1);
  }, [goToPage, currentPage]);

  const handleSortingChange = (nextSorting: SortingState) => {
    setSorting(nextSorting);
    goToPage(1, nextSorting);
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
    pageCount: lastPage,
    state: {
      pagination: {
        pageIndex: currentPage - 1,
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
            page {formatNumber(currentPage)} of {formatNumber(lastPage)}
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
