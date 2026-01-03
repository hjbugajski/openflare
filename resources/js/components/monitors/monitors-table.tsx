import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';

import { MonitorStatusBadge } from '@/components/monitors/monitor-status-badge';
import { UptimePercentage } from '@/components/monitors/uptime-percentage';
import { UptimeSparkline } from '@/components/monitors/uptime-sparkline';
import { DataTable } from '@/components/ui/data-table';
import { formatInterval } from '@/lib/format/interval';
import { formatRelativeTime } from '@/lib/format/relative-time';
import { show } from '@/routes/monitors';
import type { Monitor } from '@/types';

const columns: ColumnDef<Monitor>[] = [
  {
    accessorKey: 'name',
    header: 'name',
    cell: ({ row }) => (
      <div className="grid gap-1">
        <Link
          href={show(row.original.id).url}
          className="font-medium text-accent transition hover:text-foreground"
        >
          {row.original.name}
        </Link>
        <span className="max-w-80 truncate text-xs text-muted-foreground">{row.original.url}</span>
      </div>
    ),
    meta: { className: 'whitespace-nowrap' },
  },
  {
    id: 'status',
    header: 'status',
    cell: ({ row }) => (
      <MonitorStatusBadge
        status={row.original.latest_check?.status}
        isActive={row.original.is_active}
        hasIncident={row.original.current_incident !== null}
      />
    ),
    meta: { className: 'whitespace-nowrap' },
  },
  {
    id: 'uptime',
    header: '30d uptime',
    cell: ({ row }) =>
      row.original.daily_rollups?.length ? (
        <UptimePercentage data={row.original.daily_rollups} />
      ) : (
        <span className="text-muted-foreground">&ndash;</span>
      ),
    meta: { className: 'whitespace-nowrap' },
  },
  {
    id: 'sparkline',
    header: '',
    cell: ({ row }) =>
      row.original.daily_rollups?.length ? (
        <UptimeSparkline data={row.original.daily_rollups} height={16} />
      ) : null,
    meta: { className: 'w-full min-w-24' },
  },
  {
    accessorKey: 'interval',
    header: 'interval',
    cell: ({ row }) => {
      const interval = formatInterval(row.original.interval);
      return (
        <>
          {interval.value}
          <span className="text-muted-foreground">{interval.unit}</span>
        </>
      );
    },
    meta: { className: 'whitespace-nowrap' },
  },
  {
    id: 'latency',
    header: 'latency',
    cell: ({ row }) => {
      const latency = row.original.latest_check?.response_time_ms;
      return latency != null ? (
        <>
          {latency}
          <span className="text-muted-foreground">ms</span>
        </>
      ) : (
        <span className="text-muted-foreground">&ndash;</span>
      );
    },
    meta: { className: 'whitespace-nowrap' },
  },
  {
    id: 'last_check',
    header: 'last check',
    cell: ({ row }) =>
      row.original.latest_check ? (
        formatRelativeTime(row.original.latest_check.checked_at)
      ) : (
        <span className="text-muted-foreground">pending</span>
      ),
    meta: { className: 'whitespace-nowrap' },
  },
];

interface MonitorsTableProps {
  monitors: Monitor[];
}

export function MonitorsTable({ monitors }: MonitorsTableProps) {
  return <DataTable columns={columns} data={monitors} />;
}
