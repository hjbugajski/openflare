import type { ColumnDef } from '@tanstack/react-table';

import { ServerDataTable } from '@/components/server-data-table';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/format/date-time';
import { formatDuration } from '@/lib/format/duration';
import type { Incident, Paginated } from '@/types';

const columns: ColumnDef<Incident>[] = [
  {
    accessorKey: 'ended_at',
    header: 'status',
    cell: ({ row }) => (
      <Badge variant={row.original.ended_at ? 'secondary' : 'danger'}>
        {row.original.ended_at ? 'resolved' : 'active'}
      </Badge>
    ),
    meta: {
      className: 'whitespace-nowrap',
    },
  },
  {
    accessorKey: 'cause',
    header: 'cause',
    cell: ({ row }) => row.original.cause ?? <>&ndash;</>,
    meta: {
      className: 'w-full whitespace-nowrap',
    },
  },
  {
    id: 'duration',
    header: 'duration',
    cell: ({ row }) => formatDuration(row.original.started_at, row.original.ended_at),
    meta: {
      className: 'whitespace-nowrap',
    },
  },
  {
    accessorKey: 'started_at',
    header: 'started',
    cell: ({ row }) => formatDateTime(row.original.started_at),
    meta: {
      className: 'whitespace-nowrap',
    },
  },
  {
    accessorKey: 'ended_at',
    id: 'ended_at_display',
    header: 'ended',
    cell: ({ row }) =>
      row.original.ended_at ? formatDateTime(row.original.ended_at) : <>&ndash;</>,
    meta: {
      className: 'whitespace-nowrap',
    },
  },
];

interface IncidentsTableProps {
  incidents: Paginated<Incident>;
}

export function IncidentsTable({ incidents }: IncidentsTableProps) {
  return (
    <ServerDataTable
      columns={columns}
      paginated={incidents}
      queryParam="incidents_page"
      reloadOnly={['incidents']}
    />
  );
}
