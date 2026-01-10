import { useEffect, useRef } from 'react';

import type { StatusToolbarSummary } from '@/types';

import { cva } from 'class-variance-authority';

import { cn } from '@/lib/cn';

const stateLabel = {
  operational: 'fully operational',
  degraded: 'potential incident',
  incident: 'active incident',
} as const;

const dotVariants = cva('inline-flex h-2 w-2 shrink-0 animate-pulse rounded-full', {
  variants: {
    state: {
      operational: 'bg-success',
      degraded: 'bg-warning',
      incident: 'bg-danger',
    },
  },
  defaultVariants: {
    state: 'operational',
  },
});

const labelVariants = cva('font-semibold', {
  variants: {
    state: {
      operational: 'text-success',
      degraded: 'text-warning',
      incident: 'text-danger',
    },
  },
  defaultVariants: {
    state: 'operational',
  },
});

interface StatusToolbarProps {
  summary: StatusToolbarSummary | null | undefined;
  size?: 'sm' | 'default';
}

export function StatusToolbar({ summary, size = 'default' }: StatusToolbarProps) {
  const toolbarRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!summary) {
      document.documentElement.style.removeProperty('--status-toolbar-height');
      return;
    }

    const element = toolbarRef.current;
    if (!element) {
      return;
    }

    const updateHeight = () => {
      const height = element.getBoundingClientRect().height;
      document.documentElement.style.setProperty(
        '--status-toolbar-height',
        `${Math.ceil(height)}px`,
      );
    };

    updateHeight();

    const observer = new ResizeObserver(updateHeight);
    observer.observe(element);

    return () => {
      observer.disconnect();
      document.documentElement.style.removeProperty('--status-toolbar-height');
    };
  }, [summary]);

  if (!summary) {
    return null;
  }

  const label = stateLabel[summary.state];
  const monitorLabel =
    summary.totalMonitors === 0
      ? 'no monitors configured'
      : `${summary.activeMonitors} of ${summary.totalMonitors} monitors active`;

  return (
    <div
      ref={toolbarRef}
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
        <div className="flex items-center gap-2">
          <span className={dotVariants({ state: summary.state })} />
          <span className={labelVariants({ state: summary.state })}>{label}</span>
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
