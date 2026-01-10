import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';

import { ServerDataTable } from '@/components/server-data-table';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Divider } from '@/components/ui/divider';
import { EmptyState } from '@/components/ui/empty-state';
import { Heading } from '@/components/ui/heading';
import { Stats } from '@/components/ui/stats';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format/date-time';
import { formatDuration } from '@/lib/format/duration';
import { show } from '@/routes/monitors';
import type { IncidentWithMonitor, Paginated } from '@/types';

interface MonitorCounts {
  up: number;
  down: number;
  inactive: number;
}

interface Props {
  counts: MonitorCounts;
  incidents: Paginated<IncidentWithMonitor>;
}

const getIncidentDurationMs = (incident: IncidentWithMonitor) => {
  const start = new Date(incident.started_at).getTime();
  const end = incident.ended_at ? new Date(incident.ended_at).getTime() : Date.now();

  return end - start;
};

const columns: ColumnDef<IncidentWithMonitor>[] = [
  {
    id: 'monitor',
    accessorFn: (incident) => incident.monitor.name,
    header: 'monitor',
    cell: ({ row }) => (
      <Link
        href={show(row.original.monitor.id).url}
        className="font-medium text-accent transition hover:text-foreground"
      >
        {row.original.monitor.name}
      </Link>
    ),
    meta: {
      className: 'whitespace-nowrap',
    },
  },
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
    id: 'ended_at',
    header: 'ended',
    cell: ({ row }) =>
      row.original.ended_at ? formatDateTime(row.original.ended_at) : <>&ndash;</>,
    meta: {
      className: 'whitespace-nowrap',
    },
  },
];

export default function DashboardIndex({ counts, incidents }: Props) {
  const total = counts.up + counts.down + counts.inactive;

  return (
    <AppLayout>
      <Head title="Overview" />

      <Heading title="overview" />
      <Stats.Root>
        <Stats.Card>
          <Stats.Term>total</Stats.Term>
          <Stats.Value>{total}</Stats.Value>
        </Stats.Card>
        <Stats.Card>
          <Stats.Term>up</Stats.Term>
          <Stats.Value className="text-success">{counts.up}</Stats.Value>
        </Stats.Card>
        <Stats.Card>
          <Stats.Term>down</Stats.Term>
          <Stats.Value className={counts.down > 0 ? 'text-danger' : 'text-muted-foreground'}>
            {counts.down}
          </Stats.Value>
        </Stats.Card>
        <Stats.Card>
          <Stats.Term>inactive</Stats.Term>
          <Stats.Value className="text-muted-foreground">{counts.inactive}</Stats.Value>
        </Stats.Card>
      </Stats.Root>

      <Divider />

      <Heading title="recent incidents" description="incidents from the last 7 days" />
      <Card.Root>
        {incidents.data.length === 0 ? (
          <EmptyState className="p-8" message="no incidents" />
        ) : (
          <ServerDataTable
            columns={columns}
            paginated={incidents}
            queryParam="page"
            initialSorting={[{ id: 'started_at', desc: true }]}
          />
        )}
      </Card.Root>
    </AppLayout>
  );
}
