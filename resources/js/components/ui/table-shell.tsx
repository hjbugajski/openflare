import { type ColumnDef, type Table, flexRender } from '@tanstack/react-table';

import { IconArrowDown } from '@/components/icons/arrow-down';
import { IconArrowUp } from '@/components/icons/arrow-up';
import { IconArrowsSort } from '@/components/icons/arrows-sort';
import { cn } from '@/lib/cn';

declare module '@tanstack/react-table' {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  interface ColumnMeta<TData, TValue> {
    className?: string;
  }
}

interface TableShellProps<TData, TValue> {
  table: Table<TData>;
  columns: ColumnDef<TData, TValue>[];
}

export function TableShell<TData, TValue>({ table, columns }: TableShellProps<TData, TValue>) {
  return (
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
                      <span>{flexRender(header.column.columnDef.header, header.getContext())}</span>
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
