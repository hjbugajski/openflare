import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useCallback, useRef, useState } from 'react';

import { ChecksTable } from '@/components/monitors/checks-table';
import { DeleteMonitorDialog } from '@/components/monitors/delete-monitor-dialog';
import { IncidentsTable } from '@/components/monitors/incidents-table';
import { MonitorStatusBadge } from '@/components/monitors/monitor-status-badge';
import { NotifiersTable } from '@/components/monitors/notifiers-table';
import { UptimePercentage } from '@/components/monitors/uptime-percentage';
import { UptimeSparkline } from '@/components/monitors/uptime-sparkline';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Heading } from '@/components/ui/heading';
import { Stats } from '@/components/ui/stats';
import { ValueUnit } from '@/components/ui/value-unit';
import AppLayout from '@/layouts/app-layout';
import { formatInterval } from '@/lib/format/interval';
import { useDebouncedCallback } from '@/lib/hooks/use-debounced-callback';
import { edit } from '@/routes/monitors';
import {
  type CursorPaginated,
  type DailyUptimeRollup,
  type Incident,
  type Monitor,
  type MonitorCheck,
  type NotifierSummary,
  type PageProps,
} from '@/types';
import type {
  IncidentOpenedEvent,
  IncidentResolvedEvent,
  MonitorCheckedEvent,
} from '@/types/events';

interface Props {
  monitor: Monitor;
  checks: CursorPaginated<MonitorCheck>;
  incidents: CursorPaginated<Incident>;
  notifiers: CursorPaginated<NotifierSummary>;
  dailyRollups: DailyUptimeRollup[];
}

const RELOAD_DEBOUNCE_MS = 2000;

export default function MonitorsShow({
  monitor,
  checks,
  incidents,
  notifiers,
  dailyRollups,
}: Props) {
  const { auth } = usePage<PageProps>().props;
  const browserTimezone =
    typeof Intl !== 'undefined'
      ? (Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC')
      : 'UTC';
  const timezone = auth.user?.preferences?.timezone ?? browserTimezone;
  const [currentIncident, setCurrentIncident] = useState(monitor.current_incident);
  const [latestCheck, setLatestCheck] = useState(monitor.latest_check);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

  // Track what needs reloading, then batch into single request
  const pendingReloads = useRef<Set<string>>(new Set());

  const flushReloads = useDebouncedCallback(() => {
    if (pendingReloads.current.size === 0) return;

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

  const handleMonitorChecked = useCallback(
    (e: MonitorCheckedEvent) => {
      const newCheck: MonitorCheck = {
        id: e.check.id,
        monitor_id: e.monitor_id,
        status: e.check.status,
        status_code: e.check.status_code,
        response_time_ms: e.check.response_time_ms,
        error_message: e.check.error_message,
        checked_at: e.check.checked_at,
      };

      setLatestCheck(newCheck);
      scheduleReload('checks');
    },
    [scheduleReload],
  );

  const handleIncidentOpened = useCallback(
    (e: IncidentOpenedEvent) => {
      const newIncident: Incident = {
        id: e.incident.id,
        monitor_id: e.monitor_id,
        started_at: e.incident.started_at,
        ended_at: null,
        cause: e.incident.cause,
      };

      setCurrentIncident(newIncident);
      scheduleReload('incidents');
    },
    [scheduleReload],
  );

  const handleIncidentResolved = useCallback(() => {
    setCurrentIncident(null);
    scheduleReload('incidents');
  }, [scheduleReload]);

  useEcho<MonitorCheckedEvent>(`monitors.${monitor.id}`, '.monitor.checked', handleMonitorChecked);
  useEcho<IncidentOpenedEvent>(`monitors.${monitor.id}`, '.incident.opened', handleIncidentOpened);
  useEcho<IncidentResolvedEvent>(
    `monitors.${monitor.id}`,
    '.incident.resolved',
    handleIncidentResolved,
  );

  const status = latestCheck?.status;
  const hasIncident = currentIncident !== null;
  const interval = formatInterval(monitor.interval);

  return (
    <AppLayout>
      <Head title={monitor.name} />

      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <Heading title={monitor.name} />
            <MonitorStatusBadge
              status={status}
              isActive={monitor.is_active}
              hasIncident={hasIncident}
            />
          </div>
          <p className="text-xs text-muted-foreground">{monitor.url}</p>
        </div>
        <div className="flex gap-2">
          <Button variant="secondary" render={<Link href={edit(monitor.id).url} />}>
            edit
          </Button>
          <Button variant="destructive" onClick={() => setDeleteDialogOpen(true)}>
            delete
          </Button>
        </div>
      </div>

      <DeleteMonitorDialog
        monitorId={monitor.id}
        monitorName={monitor.name}
        open={deleteDialogOpen}
        onOpenChange={setDeleteDialogOpen}
      />

      <Card.Root>
        <Card.Header>
          <div className="flex items-center justify-between">
            <Heading level={2} title="Uptime" />
            <UptimePercentage data={dailyRollups ?? []} className="font-medium" />
          </div>
        </Card.Header>
        <Card.Content>
          <UptimeSparkline
            data={dailyRollups ?? []}
            height={32}
            className="w-full"
            timezone={timezone}
          />
          <div className="mt-2 flex justify-between text-xs text-muted-foreground">
            <span>30d ago</span>
            <span>today</span>
          </div>
        </Card.Content>
      </Card.Root>

      <Stats.Root>
        <Stats.Card>
          <Stats.Term>method</Stats.Term>
          <Stats.Value>{monitor.method}</Stats.Value>
        </Stats.Card>
        <Stats.Card>
          <Stats.Term>interval</Stats.Term>
          <Stats.Value>
            <ValueUnit value={interval.value} unit={interval.unit} />
          </Stats.Value>
        </Stats.Card>
        <Stats.Card>
          <Stats.Term>timeout</Stats.Term>
          <Stats.Value>
            <ValueUnit value={monitor.timeout} unit="s" />
          </Stats.Value>
        </Stats.Card>
        <Stats.Card>
          <Stats.Term>status</Stats.Term>
          <Stats.Value>{monitor.expected_status_code}</Stats.Value>
        </Stats.Card>
      </Stats.Root>

      <Card.Root>
        <Card.Header>
          <div className="flex items-center gap-2">
            <Heading level={2} title="notifiers" />
            <Badge variant="secondary">{notifiers.total}</Badge>
          </div>
        </Card.Header>
        <Card.Content>
          {notifiers.total === 0 ? (
            <EmptyState message="no notifiers" />
          ) : (
            <NotifiersTable monitorId={monitor.id} notifiers={notifiers} />
          )}
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Header>
          <div className="flex items-center gap-2">
            <Heading level={2} title="incidents" />
            <Badge variant="secondary">{incidents.total}</Badge>
          </div>
        </Card.Header>
        <Card.Content>
          {incidents.total === 0 ? (
            <EmptyState message="no incidents" />
          ) : (
            <IncidentsTable incidents={incidents} />
          )}
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Header>
          <div className="flex items-center gap-2">
            <Heading level={2} title="checks" />
            <Badge variant="secondary">{checks.total}</Badge>
          </div>
        </Card.Header>
        <Card.Content>
          {checks.total === 0 ? (
            <EmptyState message="no checks" />
          ) : (
            <ChecksTable checks={checks} />
          )}
        </Card.Content>
      </Card.Root>
    </AppLayout>
  );
}
