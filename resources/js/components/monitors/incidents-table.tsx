import type { ColumnDef } from '@tanstack/react-table';

import { ServerDataTable } from '@/components/server-data-table';
import { Badge } from '@/components/ui/badge';
import { ValueUnit } from '@/components/ui/value-unit';
import { formatDateTime } from '@/lib/format/date-time';
import { formatDurationParts } from '@/lib/format/duration';
import type { CursorPaginated, Incident } from '@/types';

const getIncidentDurationMs = (incident: Incident) => {
  const start = new Date(incident.started_at).getTime();
  const end = incident.ended_at ? new Date(incident.ended_at).getTime() : Date.now();

  return end - start;
};

const columns: ColumnDef<Incident>[] = [
  {
    id: 'status',
    accessorFn: (incident) => (incident.ended_at ? 'resolved' : 'active'),
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
    accessorFn: (incident) => getIncidentDurationMs(incident),
    header: 'duration',
    cell: ({ row }) => {
      const duration = formatDurationParts(row.original.started_at, row.original.ended_at);

      return <ValueUnit value={duration.value} unit={duration.unit} suffix={duration.suffix} />;
    },
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
    id: 'ended_at',
    header: 'ended',
    cell: ({ row }) =>
      row.original.ended_at ? formatDateTime(row.original.ended_at) : <>&ndash;</>,
    meta: {
      className: 'whitespace-nowrap',
    },
  },
];

interface IncidentsTableProps {
  incidents: CursorPaginated<Incident>;
}

export function IncidentsTable({ incidents }: IncidentsTableProps) {
  return (
    <ServerDataTable
      columns={columns}
      paginated={incidents}
      cursorParam="incidents_cursor"
      sortParam="incidents_sort"
      directionParam="incidents_direction"
      reloadOnly={['incidents']}
      initialSorting={[{ id: 'started_at', desc: true }]}
    />
  );
}
