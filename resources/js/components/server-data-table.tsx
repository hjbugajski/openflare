import { useCallback, useMemo } from 'react';

import { router, usePage } from '@inertiajs/react';
import {
  type ColumnDef,
  type SortingState,
  getCoreRowModel,
  useReactTable,
} from '@tanstack/react-table';
import { IconChevronDoubleLeft } from 'central-icons/IconChevronDoubleLeft';
import { IconChevronDoubleRight } from 'central-icons/IconChevronDoubleRight';
import { IconChevronGrabberVertical } from 'central-icons/IconChevronGrabberVertical';
import { IconChevronLeftSmall } from 'central-icons/IconChevronLeftSmall';
import { IconChevronRightSmall } from 'central-icons/IconChevronRightSmall';

import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { TableShell } from '@/components/ui/table-shell';
import { formatNumber } from '@/lib/format/number';
import type { Paginated } from '@/types';

// `page.url` is path-relative; the base exists only to make it parseable.
const URL_BASE = 'http://localhost';

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
  /*
   * Sort is derived from the Inertia page url on every navigation, never held
   * in local state: a non-GET redirect (a row delete landing back on the bare
   * index) drops the query string while `preserveState` keeps the component
   * mounted, and mount-time state would then keep resending params the server
   * no longer has.
   */
  const { url } = usePage();
  const sorting = useMemo<SortingState>(() => {
    const params = new URL(url, URL_BASE).searchParams;
    const sort = params.get(sortParam);

    if (!sort) {
      return initialSorting;
    }

    return [{ id: sort, desc: params.get(directionParam) === 'desc' }];
  }, [url, sortParam, directionParam, initialSorting]);

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

  const handleFirstPage = useCallback(() => {
    goToPage(1);
  }, [goToPage]);

  const handleLastPage = useCallback(() => {
    goToPage(lastPage);
  }, [goToPage, lastPage]);

  const handlePageSelect = useCallback(
    (value: unknown) => {
      goToPage(Number(value));
    },
    [goToPage],
  );

  const pages = useMemo(
    () => Array.from({ length: lastPage }, (_, index) => index + 1),
    [lastPage],
  );

  // Nothing to set locally — the visit writes the new sort to the url, which is
  // what `sorting` is read back from.
  const handleSortingChange = (
    nextSorting: SortingState | ((prev: SortingState) => SortingState),
  ) => {
    goToPage(1, typeof nextSorting === 'function' ? nextSorting(sorting) : nextSorting);
  };

  // eslint-disable-next-line react-hooks/incompatible-library
  const table = useReactTable({
    data: paginated.data,
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    manualSorting: true,
    /*
     * Two-state cycling: clearing the sort would drop the params and leave the
     * header neutral while the server still applies its own default order.
     */
    enableSortingRemoval: false,
    pageCount: lastPage,
    state: {
      pagination: {
        pageIndex: currentPage - 1,
        pageSize: paginated.per_page,
      },
      sorting,
    },
    onSortingChange: handleSortingChange,
  });

  return (
    <div className="space-y-4">
      <TableShell table={table} columns={columns} />

      {/*
       * An out-of-range page (the last row of page 2 deleted, say) comes back
       * empty with `last_page` behind `current_page`, so an emptiness-only
       * check would strand the reader with no way back.
       */}
      {paginated.data.length > 0 || canPreviousPage ? (
        <div className="flex items-center justify-between border-t border-border pt-4 text-sm text-muted-foreground">
          <div className="flex items-center gap-1">
            <span>page</span>
            <Select.Root
              value={currentPage}
              disabled={lastPage === 1}
              onValueChange={handlePageSelect}
            >
              <Select.Trigger
                aria-label="go to page"
                disabled={lastPage === 1}
                className="h-6 w-auto gap-1 border-transparent bg-transparent py-0 pr-1 pl-2 text-muted-foreground hover:bg-muted hover:text-foreground"
              >
                <Select.Value>
                  {currentPage <= lastPage ? formatNumber(currentPage) : ''}
                </Select.Value>
                <Select.Icon>
                  <IconChevronGrabberVertical className="size-3" />
                </Select.Icon>
              </Select.Trigger>
              <Select.Portal>
                <Select.Positioner>
                  <Select.Popup>
                    {pages.map((page) => (
                      <Select.Item key={page} value={page} className="py-1">
                        <Select.ItemText>{formatNumber(page)}</Select.ItemText>
                      </Select.Item>
                    ))}
                  </Select.Popup>
                </Select.Positioner>
              </Select.Portal>
            </Select.Root>
            <span>of {formatNumber(lastPage)}</span>
          </div>
          <div className="flex gap-2">
            <Button
              variant="tertiary"
              size="icon"
              disabled={!canPreviousPage}
              onClick={handleFirstPage}
            >
              <span className="sr-only">first</span>
              <IconChevronDoubleLeft />
            </Button>
            <Button
              variant="tertiary"
              size="icon"
              disabled={!canPreviousPage}
              onClick={handlePreviousPage}
            >
              <span className="sr-only">previous</span>
              <IconChevronLeftSmall />
            </Button>
            <Button variant="tertiary" size="icon" disabled={!canNextPage} onClick={handleNextPage}>
              <span className="sr-only">next</span>
              <IconChevronRightSmall />
            </Button>
            <Button variant="tertiary" size="icon" disabled={!canNextPage} onClick={handleLastPage}>
              <span className="sr-only">last</span>
              <IconChevronDoubleRight />
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
