import { useCallback, useEffect } from 'react';

import { render } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useUserChannel } from '@/lib/hooks/use-user-channel';
import type { MonitorCheckedEvent } from '@/types/events';

const { listeners, subscribes } = vi.hoisted(() => ({
  listeners: new Map<string, (payload: unknown) => void>(),
  subscribes: { count: 0 },
}));

/*
 * Mirrors `useEcho`'s own memoization: the handler it listens with is wrapped in
 * a `useCallback` keyed on the dependency argument, which defaults to `[]`. A
 * caller that omits the argument is therefore stuck with the first-render
 * closure no matter how often it re-renders. The subscribe effect is keyed on
 * that memoized handler, so a changing identity rejoins the shared channel.
 */
vi.mock('@laravel/echo-react', () => ({
  useEcho: (
    channel: string,
    event: string,
    callback: (payload: unknown) => void,
    dependencies: unknown[] = [],
  ) => {
    // oxlint-disable-next-line react-hooks/exhaustive-deps
    const listener = useCallback(callback, dependencies);

    listeners.set(event, listener);

    useEffect(() => {
      subscribes.count += 1;
    }, [channel, listener]);
  },
}));

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { auth: { user: { uuid: 'u1' } } } }),
}));

const noop = () => {};

function Harness({ onMonitorChecked }: { onMonitorChecked: (event: MonitorCheckedEvent) => void }) {
  useUserChannel({
    onMonitorChecked,
    onIncidentOpened: noop,
    onIncidentResolved: noop,
  });

  return null;
}

function InlineHarness({
  onMonitorChecked,
}: {
  onMonitorChecked: (event: MonitorCheckedEvent) => void;
}) {
  /*
   * Stands in for a caller the compiler bails out of (`ServerDataTable` and
   * anything else reaching for `useReactTable`). Compiled, the arrows below
   * would be memoized and the hazard invisible.
   */
  'use no memo';

  useUserChannel({
    onMonitorChecked: (event) => onMonitorChecked(event),
    onIncidentOpened: () => {},
    onIncidentResolved: () => {},
  });

  return null;
}

describe('useUserChannel', () => {
  beforeEach(() => {
    listeners.clear();
    subscribes.count = 0;
  });

  it('listens with the latest handler after a re-render', () => {
    const first = vi.fn();
    const second = vi.fn();

    const { rerender } = render(<Harness onMonitorChecked={first} />);

    rerender(<Harness onMonitorChecked={second} />);

    listeners.get('.monitor.checked')?.({});

    expect(second).toHaveBeenCalledTimes(1);
    expect(first).not.toHaveBeenCalled();
  });

  it('stays subscribed when the caller passes inline handlers', () => {
    const handler = vi.fn();

    const { rerender } = render(<InlineHarness onMonitorChecked={handler} />);

    // One per listener; the three share a channel, so a resubscribe would drop
    // the refcount to zero and really leave it.
    expect(subscribes.count).toBe(3);

    rerender(<InlineHarness onMonitorChecked={handler} />);

    expect(subscribes.count).toBe(3);

    listeners.get('.monitor.checked')?.({});

    expect(handler).toHaveBeenCalledTimes(1);
  });
});
