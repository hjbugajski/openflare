import { useCallback, useRef } from 'react';

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
export function useUserChannel(handlers: UserChannelHandlers) {
  const { auth } = usePage<PageProps>().props;
  const channel = `users.${auth.user?.uuid ?? ''}`;

  /*
   * `useEcho` keys its subscribe effect on the handler identity, and all three
   * listeners share one channel — a caller passing inline handlers would leave
   * and rejoin the channel on every render. Reading the handlers from a ref
   * keeps the identity `useEcho` sees fixed while still calling the latest one.
   */
  const handlersRef = useRef(handlers);
  handlersRef.current = handlers;

  const handleMonitorChecked = useCallback((event: MonitorCheckedEvent) => {
    handlersRef.current.onMonitorChecked(event);
  }, []);

  const handleIncidentOpened = useCallback((event: IncidentOpenedEvent) => {
    handlersRef.current.onIncidentOpened(event);
  }, []);

  const handleIncidentResolved = useCallback((event: IncidentResolvedEvent) => {
    handlersRef.current.onIncidentResolved(event);
  }, []);

  useEcho<MonitorCheckedEvent>(channel, '.monitor.checked', handleMonitorChecked);
  useEcho<IncidentOpenedEvent>(channel, '.incident.opened', handleIncidentOpened);
  useEcho<IncidentResolvedEvent>(channel, '.incident.resolved', handleIncidentResolved);
}
