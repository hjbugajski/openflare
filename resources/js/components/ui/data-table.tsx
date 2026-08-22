import { useState } from 'react';

import { type ColumnDef, type RowData, type SortingState, useTable } from '@tanstack/react-table';

import { type TableFeatures, features } from '@/components/ui/table-features';
import { TableShell } from '@/components/ui/table-shell';

interface DataTableProps<TData extends RowData> {
  columns: ColumnDef<TableFeatures, TData>[];
  data: TData[];
  initialSorting?: SortingState;
}

export function DataTable<TData extends RowData>({
  columns,
  data,
  initialSorting = [],
}: DataTableProps<TData>) {
  const [sorting, setSorting] = useState<SortingState>(initialSorting);

  const table = useTable({
    features,
    data,
    columns,
    state: { sorting },
    onSortingChange: setSorting,
  });

  return <TableShell table={table} columns={columns} />;
}
