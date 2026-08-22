import { type ColumnDef, type RowData, type Table, flexRender } from '@tanstack/react-table';
import { IconArrowDown } from 'central-icons/IconArrowDown';
import { IconArrowTopBottom } from 'central-icons/IconArrowTopBottom';
import { IconArrowUp } from 'central-icons/IconArrowUp';

import type { TableFeatures } from '@/components/ui/table-features';
import { cn } from '@/lib/cn';

interface TableShellProps<TData extends RowData> {
  table: Table<TableFeatures, TData>;
  columns: ColumnDef<TableFeatures, TData>[];
}

export function TableShell<TData extends RowData>({ table, columns }: TableShellProps<TData>) {
  /*
   * The TanStack table instance is referentially stable and mutates internally,
   * so the React Compiler must not memoize this component on it — memoized
   * output would freeze on the first page of data.
   */
  'use no memo';

  /*
   * relative makes the scroller the containing block for absolutely
   * positioned descendants (e.g. Tailwind's sr-only labels), so they clip
   * here instead of stretching the document sideways on narrow viewports.
   */
  return (
    <div className="relative overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          {table.getHeaderGroups().map((headerGroup) => (
            <tr key={headerGroup.id} className="border-b border-border">
              {headerGroup.headers.map((header) => {
                const sortDirection = header.column.getIsSorted();

                return (
                  <th
                    key={header.id}
                    // WAI-ARIA: unsorted columns omit the attribute rather than declaring "none"
                    aria-sort={
                      sortDirection === 'asc'
                        ? 'ascending'
                        : sortDirection === 'desc'
                          ? 'descending'
                          : undefined
                    }
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
                          {sortDirection === 'asc' ? (
                            <IconArrowUp className="size-3" />
                          ) : sortDirection === 'desc' ? (
                            <IconArrowDown className="size-3" />
                          ) : (
                            <IconArrowTopBottom className="size-3" />
                          )}
                        </span>
                      </button>
                    ) : (
                      flexRender(header.column.columnDef.header, header.getContext())
                    )}
                  </th>
                );
              })}
            </tr>
          ))}
        </thead>
        <tbody>
          {table.getRowModel().rows.length ? (
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
              <td colSpan={columns.length} className="px-3 py-8 text-center text-muted-foreground">
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
  );
}
