import type { ColumnDef } from '@tanstack/react-table';

import { ServerDataTable } from '@/components/server-data-table';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/format/date-time';
import type { MonitorCheck, Paginated } from '@/types';

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
    cell: ({ row }) => (
      <>
        {row.original.response_time_ms}
        <span className="text-muted-foreground">ms</span>
      </>
    ),
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
  checks: Paginated<MonitorCheck>;
}

export function ChecksTable({ checks }: ChecksTableProps) {
  return (
    <ServerDataTable
      columns={columns}
      paginated={checks}
      queryParam="checks_page"
      reloadOnly={['checks']}
    />
  );
}
