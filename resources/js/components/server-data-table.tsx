import { router } from '@inertiajs/react';
import { type ColumnDef, flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';

import { IconChevronLeft } from '@/components/icons/chevron-left';
import { IconChevronRight } from '@/components/icons/chevron-right';
import { Button } from '@/components/ui/button';
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
  reloadOnly?: string[];
}

export function ServerDataTable<TData, TValue>({
  columns,
  paginated,
  queryParam = 'page',
  reloadOnly,
}: ServerDataTableProps<TData, TValue>) {
  // eslint-disable-next-line react-hooks/incompatible-library -- compiler auto-skips, acknowledged
  const table = useReactTable({
    data: paginated.data,
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: paginated.last_page,
    state: {
      pagination: {
        pageIndex: paginated.current_page - 1,
        pageSize: paginated.per_page,
      },
    },
  });

  const goToPage = (page: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set(queryParam, String(page));

    router.visit(url.pathname + url.search, {
      preserveState: true,
      preserveScroll: true,
      only: reloadOnly,
    });
  };

  const canPreviousPage = paginated.current_page > 1;
  const canNextPage = paginated.current_page < paginated.last_page;

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
                    {header.isPlaceholder
                      ? null
                      : flexRender(header.column.columnDef.header, header.getContext())}
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
          <p className="text-sm text-muted-foreground">
            page {paginated.current_page} of {paginated.last_page}
          </p>
          <div className="flex gap-2">
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
          </div>
        </div>
      ) : null}
    </div>
  );
}
