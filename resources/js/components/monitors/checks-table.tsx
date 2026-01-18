import type { ColumnDef } from '@tanstack/react-table';

import { ServerDataTable } from '@/components/server-data-table';
import { Badge } from '@/components/ui/badge';
import { ValueUnit } from '@/components/ui/value-unit';
import { formatDateTime } from '@/lib/format/date-time';
import type { CursorPaginated, MonitorCheck } from '@/types';

const columns: ColumnDef<MonitorCheck>[] = [
  {
    accessorKey: 'status',
    header: 'status',
    cell: ({ row }) => (
      <Badge variant={row.original.status === 'up' ? 'success' : 'danger'}>
        {row.original.status === 'up' ? 'UP' : 'DN'}
      </Badge>
    ),
  },
  {
    accessorKey: 'status_code',
    header: 'code',
    cell: ({ row }) => (row.original.status_code > 0 ? row.original.status_code : <>&ndash;</>),
  },
  {
    accessorKey: 'response_time_ms',
    header: 'latency',
    cell: ({ row }) => <ValueUnit value={row.original.response_time_ms} unit="ms" />,
  },
  {
    accessorKey: 'error_message',
    header: 'error',
    cell: ({ row }) => row.original.error_message ?? <>&ndash;</>,
    meta: {
      className: 'w-full whitespace-nowrap truncate',
    },
  },
  {
    accessorKey: 'checked_at',
    header: 'timestamp',
    cell: ({ row }) => formatDateTime(row.original.checked_at),
    meta: {
      className: 'whitespace-nowrap',
    },
  },
];

interface ChecksTableProps {
  checks: CursorPaginated<MonitorCheck>;
}

export function ChecksTable({ checks }: ChecksTableProps) {
  return (
    <ServerDataTable
      columns={columns}
      paginated={checks}
      cursorParam="checks_cursor"
      sortParam="checks_sort"
      directionParam="checks_direction"
      reloadOnly={['checks']}
      initialSorting={[{ id: 'checked_at', desc: true }]}
    />
  );
}
