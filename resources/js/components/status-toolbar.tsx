import type { StatusToolbarSummary } from '@/types';

import { cn } from '@/lib/cn';

const stateStyles = {
  operational: {
    label: 'fully operational',
    dot: 'bg-emerald-400',
  },
  degraded: {
    label: 'potential incident',
    dot: 'bg-yellow-400',
  },
  incident: {
    label: 'active incident',
    dot: 'bg-red-500',
  },
} as const;

interface StatusToolbarProps {
  summary: StatusToolbarSummary | null | undefined;
  size?: 'sm' | 'default';
}

export function StatusToolbar({ summary, size = 'default' }: StatusToolbarProps) {
  if (!summary) {
    return null;
  }

  const state = stateStyles[summary.state];
  const monitorLabel =
    summary.totalMonitors === 0
      ? 'no monitors configured'
      : `${summary.activeMonitors} of ${summary.totalMonitors} monitors active`;

  return (
    <div
      role="status"
      aria-live="polite"
      className="fixed bottom-0 left-0 z-40 w-full border-t border-border bg-background-secondary/95 backdrop-blur"
    >
      <div
        className={cn(
          'mx-auto flex w-full flex-wrap items-center justify-between gap-3 px-4 py-3 text-xs',
          {
            'max-w-2xl': size === 'sm',
            'max-w-6xl': size === 'default',
          },
        )}
      >
        <div className="flex items-center gap-2 text-foreground">
          <span className={cn('size-2 animate-pulse rounded-full', state.dot)} />
          <span className="font-semibold">{state.label}</span>
        </div>
        <div className="flex flex-wrap items-center gap-3 text-muted-foreground">
          <span>{monitorLabel}</span>
          {summary.activeIncidentCount > 0 ? (
            <span>{summary.activeIncidentCount} active incidents</span>
          ) : null}
          {summary.activeIncidentCount === 0 && summary.recentFailureCount > 0 ? (
            <span>{summary.recentFailureCount} recent failed checks</span>
          ) : null}
        </div>
      </div>
    </div>
  );
}
