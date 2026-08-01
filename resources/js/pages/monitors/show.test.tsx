import type { ReactNode } from 'react';

import { act, render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import MonitorsShow from '@/pages/monitors/show';
import type { DailyUptimeRollup, Monitor, Paginated } from '@/types';
import type { MonitorCheckedEvent } from '@/types/events';

const { reload, routerEvents, echoListeners } = vi.hoisted(() => ({
  reload: vi.fn(),
  routerEvents: new Map<string, (event: unknown) => void>(),
  echoListeners: new Map<string, (payload: unknown) => void>(),
}));

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, href }: { children: ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  ),
  router: {
    reload,
    delete: vi.fn(),
    on: (type: string, callback: (event: unknown) => void) => {
      routerEvents.set(type, callback);

      return () => routerEvents.delete(type);
    },
  },
  usePage: () => ({ url: '/monitors/m1', props: { auth: { user: null } } }),
}));

vi.mock('@laravel/echo-react', () => ({
  useEcho: (_channel: string, event: string, callback: (payload: unknown) => void) => {
    echoListeners.set(event, callback);
  },
}));

vi.mock('@/layouts/app-layout', () => ({
  default: ({ children }: { children: ReactNode }) => children,
}));

const monitor: Monitor = {
  id: 'm1',
  name: 'example',
  url: 'https://example.com',
  method: 'GET',
  interval: 60,
  timeout: 5,
  is_active: true,
  expected_status_code: 200,
  failure_confirmation_threshold: 1,
  recovery_confirmation_threshold: 1,
  last_checked_at: null,
  latest_check: null,
  current_incident: null,
};

const NO_ROLLUPS: DailyUptimeRollup[] = [];

function emptyPage<T>(): Paginated<T> {
  return { data: [], current_page: 1, last_page: 1, per_page: 25, total: 0 };
}

function renderShow() {
  render(
    <MonitorsShow
      monitor={monitor}
      checks={emptyPage()}
      incidents={emptyPage()}
      notifiers={emptyPage()}
      dailyRollups={NO_ROLLUPS}
    />,
  );
}

function emitCheck() {
  const event: MonitorCheckedEvent = {
    monitor_id: 'm1',
    check: {
      id: 'c1',
      status: 'up',
      status_code: 200,
      response_time_ms: 12,
      error_message: null,
      checked_at: '2026-01-01T00:00:00Z',
    },
  };

  act(() => {
    echoListeners.get('.monitor.checked')?.(event);
  });
}

// Inertia forces `async: true` on reloads, so a synchronous visit stands in for
// a pagination click.
function emitVisit(type: 'start' | 'finish', async = false) {
  act(() => {
    routerEvents.get(type)?.({ detail: { visit: { async } } });
  });
}

function advancePastDebounce() {
  act(() => {
    vi.advanceTimersByTime(2000);
  });
}

describe('MonitorsShow realtime reloads', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    reload.mockClear();
    routerEvents.clear();
    echoListeners.clear();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('reloads the batched props after the debounce', () => {
    renderShow();
    emitCheck();
    advancePastDebounce();

    expect(reload).toHaveBeenCalledWith({ only: ['checks'] });
  });

  it('holds the reload until an in-flight navigation lands', () => {
    renderShow();
    emitCheck();
    emitVisit('start');
    advancePastDebounce();

    // Firing here would resolve against the pre-click url and rewind the table.
    expect(reload).not.toHaveBeenCalled();

    emitVisit('finish');
    advancePastDebounce();

    expect(reload).toHaveBeenCalledWith({ only: ['checks'] });
  });

  it('ignores a reload of its own in the in-flight count', () => {
    renderShow();
    emitCheck();
    emitVisit('start', true);
    advancePastDebounce();

    expect(reload).toHaveBeenCalledTimes(1);
  });
});
