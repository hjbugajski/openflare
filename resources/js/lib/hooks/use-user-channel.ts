import { usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';

import type { PageProps } from '@/types';
import type {
  IncidentOpenedEvent,
  IncidentResolvedEvent,
  MonitorCheckedEvent,
} from '@/types/events';

interface UserChannelHandlers {
  onMonitorChecked: (event: MonitorCheckedEvent) => void;
  onIncidentOpened: (event: IncidentOpenedEvent) => void;
  onIncidentResolved: (event: IncidentResolvedEvent) => void;
}

/**
 * Subscribes to the current user's private broadcast channel for monitor/incident
 * events. Safe to call before `auth.user` is hydrated - subscribes to an empty
 * channel name and relies on the handlers being no-ops until a real user exists.
 */
export function useUserChannel({
  onMonitorChecked,
  onIncidentOpened,
  onIncidentResolved,
}: UserChannelHandlers) {
  const { auth } = usePage<PageProps>().props;
  const channel = `users.${auth.user?.uuid ?? ''}`;

  useEcho<MonitorCheckedEvent>(channel, '.monitor.checked', onMonitorChecked);
  useEcho<IncidentOpenedEvent>(channel, '.incident.opened', onIncidentOpened);
  useEcho<IncidentResolvedEvent>(channel, '.incident.resolved', onIncidentResolved);
}
