import { Head, Link, router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import type { ColumnDef } from '@tanstack/react-table';
import { useCallback, useRef } from 'react';

import { ServerDataTable } from '@/components/server-data-table';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Divider } from '@/components/ui/divider';
import { EmptyState } from '@/components/ui/empty-state';
import { Heading } from '@/components/ui/heading';
import { Stats } from '@/components/ui/stats';
import { ValueUnit } from '@/components/ui/value-unit';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format/date-time';
import { formatDurationParts } from '@/lib/format/duration';
import { formatNumber } from '@/lib/format/number';
import { useDebouncedCallback } from '@/lib/hooks/use-debounced-callback';
import { show } from '@/routes/monitors';
import type { CursorPaginated, IncidentWithMonitor } from '@/types';
import type {
  IncidentOpenedEvent,
  IncidentResolvedEvent,
  MonitorCheckedEvent,
} from '@/types/events';

interface MonitorCounts {
  up: number;
  down: number;
  inactive: number;
}

interface Props {
  counts: MonitorCounts;
  incidents: CursorPaginated<IncidentWithMonitor>;
  monitorIds: string[];
}

const RELOAD_DEBOUNCE_MS = 2000;

function MonitorChannelListener({
  monitorId,
  onMonitorChecked,
  onIncidentOpened,
  onIncidentResolved,
}: {
  monitorId: string;
  onMonitorChecked: (event: MonitorCheckedEvent) => void;
  onIncidentOpened: (event: IncidentOpenedEvent) => void;
  onIncidentResolved: (event: IncidentResolvedEvent) => void;
}) {
  useEcho<MonitorCheckedEvent>(`monitors.${monitorId}`, '.monitor.checked', onMonitorChecked);
  useEcho<IncidentOpenedEvent>(`monitors.${monitorId}`, '.incident.opened', onIncidentOpened);
  useEcho<IncidentResolvedEvent>(`monitors.${monitorId}`, '.incident.resolved', onIncidentResolved);

  return null;
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

export default function DashboardIndex({ counts, incidents, monitorIds }: Props) {
  const total = counts.up + counts.down + counts.inactive;

  const pendingReloads = useRef<Set<string>>(new Set());

  const flushReloads = useDebouncedCallback(() => {
    if (pendingReloads.current.size === 0) {
      return;
    }

    const only = Array.from(pendingReloads.current);
    pendingReloads.current.clear();

    router.reload({ only });
  }, RELOAD_DEBOUNCE_MS);

  const scheduleReload = useCallback(
    (key: string) => {
      pendingReloads.current.add(key);
      flushReloads();
    },
    [flushReloads],
  );

  const handleMonitorChecked = useCallback(() => {
    scheduleReload('counts');
  }, [scheduleReload]);

  const handleIncidentOpened = useCallback(() => {
    scheduleReload('counts');
    scheduleReload('incidents');
  }, [scheduleReload]);

  const handleIncidentResolved = useCallback(() => {
    scheduleReload('counts');
    scheduleReload('incidents');
  }, [scheduleReload]);

  return (
    <AppLayout>
      <Head title="Overview" />

      {monitorIds.map((monitorId) => (
        <MonitorChannelListener
          key={monitorId}
          monitorId={monitorId}
          onMonitorChecked={handleMonitorChecked}
          onIncidentOpened={handleIncidentOpened}
          onIncidentResolved={handleIncidentResolved}
        />
      ))}

      <Heading title="overview" />
      <Stats.Root>
        <Stats.Card>
          <Stats.Term>total</Stats.Term>
          <Stats.Value>{formatNumber(total)}</Stats.Value>
        </Stats.Card>
        <Stats.Card>
          <Stats.Term>up</Stats.Term>
          <Stats.Value className="text-success">{formatNumber(counts.up)}</Stats.Value>
        </Stats.Card>
        <Stats.Card>
          <Stats.Term>down</Stats.Term>
          <Stats.Value className={counts.down > 0 ? 'text-danger' : 'text-muted-foreground'}>
            {formatNumber(counts.down)}
          </Stats.Value>
        </Stats.Card>
        <Stats.Card>
          <Stats.Term>inactive</Stats.Term>
          <Stats.Value className="text-muted-foreground">{formatNumber(counts.inactive)}</Stats.Value>
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
            initialSorting={[{ id: 'started_at', desc: true }]}
          />
        )}
      </Card.Root>
    </AppLayout>
  );
}
