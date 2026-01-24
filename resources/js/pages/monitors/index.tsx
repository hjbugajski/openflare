import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useCallback, useRef } from 'react';

import { IconGrid } from '@/components/icons/grid';
import { IconTable } from '@/components/icons/table';
import { MonitorStatusBadge } from '@/components/monitors/monitor-status-badge';
import { MonitorsTable } from '@/components/monitors/monitors-table';
import { UptimePercentage } from '@/components/monitors/uptime-percentage';
import { UptimeSparkline } from '@/components/monitors/uptime-sparkline';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Heading } from '@/components/ui/heading';
import { ToggleGroup } from '@/components/ui/toggle-group';
import { Tooltip } from '@/components/ui/tooltip';
import { ValueUnit } from '@/components/ui/value-unit';
import AppLayout from '@/layouts/app-layout';
import { formatInterval } from '@/lib/format/interval';
import { formatNumber } from '@/lib/format/number';
import { formatRelativeTime } from '@/lib/format/relative-time';
import { useDebouncedCallback } from '@/lib/hooks/use-debounced-callback';
import { usePreferencePatch } from '@/lib/hooks/use-preference-patch';
import { create, show } from '@/routes/monitors';
import { type Monitor, type MonitorViewMode, type PageProps } from '@/types';
import type {
  IncidentOpenedEvent,
  IncidentResolvedEvent,
  MonitorCheckedEvent,
} from '@/types/events';

interface Props {
  monitors: Monitor[];
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

function MonitorCard({ monitor, timezone }: { monitor: Monitor; timezone: string }) {
  const status = monitor.latest_check?.status;
  const hasIncident = monitor.current_incident !== null;
  const interval = formatInterval(monitor.interval);

  return (
    <Link href={show(monitor.id).url} className="w-full">
      <Card.Root>
        <Card.Header className="flex flex-row items-start justify-between gap-2">
          <div className="min-w-0 flex-1">
            <Heading level={2} title={monitor.name} className="truncate" />
            <Card.Description className="mt-1 truncate text-xs">{monitor.url}</Card.Description>
          </div>
          <MonitorStatusBadge
            status={status}
            isActive={monitor.is_active}
            hasIncident={hasIncident}
          />
        </Card.Header>

        <Card.Content>
          <div className="space-y-1">
            <div className="flex items-center justify-between text-sm">
              <span className="inline-flex items-center gap-1">
                <span aria-hidden className="text-accent">
                  &gt;
                </span>
                <ValueUnit value={30} unit="d" />
                <span>uptime</span>
              </span>
              <UptimePercentage data={monitor.daily_rollups ?? []} className="font-medium" />
            </div>
            <UptimeSparkline data={monitor.daily_rollups ?? []} height={16} timezone={timezone} />
          </div>
        </Card.Content>

        <Card.Footer className="gap-1 whitespace-nowrap text-foreground">
          <span className="inline-flex items-baseline gap-1">
            <span>every</span>
            <ValueUnit value={interval.value} unit={interval.unit} />
          </span>
          {monitor.latest_check ? (
            <>
              <span>•</span>
              <ValueUnit value={monitor.latest_check.response_time_ms || 0} unit="ms" />
              <span>•</span>
              {(() => {
                const relativeTime = formatRelativeTime(monitor.latest_check.checked_at, {
                  format: 'parts',
                });

                return relativeTime ? (
                  <ValueUnit
                    value={relativeTime.value}
                    unit={relativeTime.unit}
                    suffix={relativeTime.suffix}
                  />
                ) : (
                  <span>{formatRelativeTime(monitor.latest_check.checked_at)}</span>
                );
              })()}
            </>
          ) : (
            <>
              <span>•</span>
              <span>awaiting check</span>
            </>
          )}
        </Card.Footer>
      </Card.Root>
    </Link>
  );
}

export default function MonitorsIndex({ monitors }: Props) {
  const { auth } = usePage<PageProps>().props;
  const defaultView: MonitorViewMode = auth.user!.preferences?.monitors_view ?? 'cards';
  const browserTimezone =
    typeof Intl !== 'undefined'
      ? (Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC')
      : 'UTC';
  const timezone = auth.user?.preferences?.timezone ?? browserTimezone;
  const [viewMode, setViewMode] = usePreferencePatch('monitors_view', defaultView);

  const sortedMonitors = [...monitors].sort((left, right) =>
    left.name.localeCompare(right.name, undefined, { sensitivity: 'base' }),
  );

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
    scheduleReload('monitors');
  }, [scheduleReload]);

  const handleIncidentOpened = useCallback(() => {
    scheduleReload('monitors');
  }, [scheduleReload]);

  const handleIncidentResolved = useCallback(() => {
    scheduleReload('monitors');
  }, [scheduleReload]);

  const handleViewChange = (value: string[]) => {
    if (value.length > 0) {
      setViewMode(value[0] as MonitorViewMode);
    }
  };

  return (
    <AppLayout>
      <Head title="Monitors" />

      {sortedMonitors.map((monitor) => (
        <MonitorChannelListener
          key={monitor.id}
          monitorId={monitor.id}
          onMonitorChecked={handleMonitorChecked}
          onIncidentOpened={handleIncidentOpened}
          onIncidentResolved={handleIncidentResolved}
        />
      ))}

      <div className="flex items-start justify-between gap-4">
        <div className="flex items-center gap-2">
          <Heading title="monitors" />
          <Badge variant="secondary">{formatNumber(monitors.length)}</Badge>
        </div>
        <div className="flex items-center gap-2">
          <Tooltip.Provider>
            <ToggleGroup.Root value={[viewMode]} onValueChange={handleViewChange}>
              <Tooltip.Root>
                <Tooltip.Trigger
                  render={
                    <ToggleGroup.Item value="cards" aria-label="Card view">
                      <IconGrid className="size-4" />
                    </ToggleGroup.Item>
                  }
                />
                <Tooltip.Portal>
                  <Tooltip.Positioner>
                    <Tooltip.Popup>card view</Tooltip.Popup>
                  </Tooltip.Positioner>
                </Tooltip.Portal>
              </Tooltip.Root>
              <Tooltip.Root>
                <Tooltip.Trigger
                  render={
                    <ToggleGroup.Item value="table" aria-label="Table view">
                      <IconTable className="size-4" />
                    </ToggleGroup.Item>
                  }
                />
                <Tooltip.Portal>
                  <Tooltip.Positioner>
                    <Tooltip.Popup>table view</Tooltip.Popup>
                  </Tooltip.Positioner>
                </Tooltip.Portal>
              </Tooltip.Root>
            </ToggleGroup.Root>
          </Tooltip.Provider>
          <Button render={<Link href={create().url} />}>add monitor</Button>
        </div>
      </div>

      {monitors.length === 0 ? (
        <Card.Root>
          <EmptyState className="p-8" message="no monitors configured" />
        </Card.Root>
      ) : viewMode === 'cards' ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {sortedMonitors.map((monitor) => (
            <MonitorCard key={monitor.id} monitor={monitor} timezone={timezone} />
          ))}
        </div>
      ) : (
        <Card.Root className="overflow-hidden">
          <MonitorsTable monitors={sortedMonitors} timezone={timezone} />
        </Card.Root>
      )}
    </AppLayout>
  );
}
