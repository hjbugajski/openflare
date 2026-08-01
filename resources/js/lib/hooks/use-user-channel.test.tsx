import { useCallback } from 'react';

import { render } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useUserChannel } from '@/lib/hooks/use-user-channel';
import type { MonitorCheckedEvent } from '@/types/events';

const { listeners } = vi.hoisted(() => ({
  listeners: new Map<string, (payload: unknown) => void>(),
}));

/*
 * Mirrors `useEcho`'s own memoization: the handler it listens with is wrapped in
 * a `useCallback` keyed on the dependency argument, which defaults to `[]`. A
 * caller that omits the argument is therefore stuck with the first-render
 * closure no matter how often it re-renders.
 */
vi.mock('@laravel/echo-react', () => ({
  useEcho: (
    _channel: string,
    event: string,
    callback: (payload: unknown) => void,
    dependencies: unknown[] = [],
  ) => {
    // oxlint-disable-next-line react-hooks/exhaustive-deps
    listeners.set(event, useCallback(callback, dependencies));
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

describe('useUserChannel', () => {
  beforeEach(() => {
    listeners.clear();
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
});
