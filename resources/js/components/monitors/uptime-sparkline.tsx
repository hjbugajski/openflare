import { useMemo } from 'react';

import { Tooltip } from '@/components/ui/tooltip';
import { cn } from '@/lib/cn';
import { formatNumber } from '@/lib/format/number';
import { resolveRollupTimezone } from '@/lib/timezone';
import type { DailyUptimeRollup } from '@/types';

const FULL_HEIGHT_STYLE = { height: '100%' };

interface UptimeSparklineProps {
  data: DailyUptimeRollup[] | undefined;
  days?: number;
  height?: number;
  className?: string;
  timezone?: string;
}

function getBarStyle(upPercent: number, height: number) {
  const downPercent = 100 - upPercent;

  if (downPercent === 0) {
    return { upHeight: '100%', downHeight: '0%' };
  }

  const minDownPx = Math.max(3, height * 0.15);
  const minDownPercent = (minDownPx / height) * 100;

  // sqrt scale emphasizes small failures without underrepresenting major outages
  const scaledDownPercent = Math.max(minDownPercent, Math.sqrt(downPercent) * 10);
  const finalDownPercent = Math.min(Math.max(scaledDownPercent, downPercent), 100);
  const finalUpPercent = 100 - finalDownPercent;

  return {
    upHeight: `${finalUpPercent}%`,
    downHeight: `${finalDownPercent}%`,
  };
}

function getTodayString(timezone: string) {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date());
}

function buildDateRange(days: number, timezone: string) {
  const today = getTodayString(timezone);
  const parts = today.split('-');

  if (parts.length !== 3) {
    return [];
  }

  const [yearPart, monthPart, dayPart] = parts;
  const year = Number(yearPart);
  const month = Number(monthPart);
  const day = Number(dayPart);

  if (Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day)) {
    return [];
  }

  const baseDate = new Date(Date.UTC(year, month - 1, day));
  const dates: string[] = [];

  for (let i = days - 1; i >= 0; i--) {
    const date = new Date(baseDate);
    date.setUTCDate(baseDate.getUTCDate() - i);

    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
    const day = String(date.getUTCDate()).padStart(2, '0');

    dates.push(`${year}-${month}-${day}`);
  }

  return dates;
}

export function UptimeSparkline({
  data = [],
  days = 30,
  height = 24,
  className,
  timezone,
}: UptimeSparklineProps) {
  const resolvedTimezone = resolveRollupTimezone(timezone);
  const rollupMap = useMemo(() => new Map(data.map((r) => [r.date.slice(0, 10), r])), [data]);
  const dates = useMemo(() => buildDateRange(days, resolvedTimezone), [days, resolvedTimezone]);
  const heightStyle = useMemo(() => ({ height }), [height]);

  return (
    <Tooltip.Provider>
      <div
        className={cn('flex items-end gap-px', className)}
        style={heightStyle}
        role="img"
        aria-label={`Uptime over the last ${days} days`}
      >
        {dates.map((date) => {
          const rollup = rollupMap.get(date);

          if (!rollup || rollup.total_checks === 0) {
            return (
              <Tooltip.Root key={date}>
                <Tooltip.Trigger
                  className="min-w-0.5 flex-1 bg-muted hover:bg-muted-foreground"
                  style={FULL_HEIGHT_STYLE}
                >
                  <span className="sr-only">{date}: No data</span>
                </Tooltip.Trigger>
                <Tooltip.Portal>
                  <Tooltip.Positioner>
                    <Tooltip.Popup>
                      {date}
                      <br />
                      <span aria-hidden className="text-muted-foreground">
                        [<span className="mx-1">no data</span>]
                      </span>
                    </Tooltip.Popup>
                  </Tooltip.Positioner>
                </Tooltip.Portal>
              </Tooltip.Root>
            );
          }

          const upPercent = Number(rollup.uptime_percentage);
          const downPercent = 100 - upPercent;
          const { upHeight, downHeight } = getBarStyle(upPercent, height);

          return (
            <Tooltip.Root key={date}>
              <Tooltip.Trigger
                className="flex min-w-0.5 flex-1 flex-col justify-end overflow-hidden hover:opacity-80"
                style={FULL_HEIGHT_STYLE}
              >
                <span className="sr-only">
                  {date}: {upPercent.toFixed(2)}% up
                </span>
                {/* oxlint-disable react-perf/jsx-no-new-object-as-prop */}
                {downPercent > 0 ? (
                  <div className="w-full bg-danger" style={{ height: downHeight }} />
                ) : null}
                {upPercent > 0 ? (
                  <div className="w-full bg-success" style={{ height: upHeight }} />
                ) : null}
                {/* oxlint-enable react-perf/jsx-no-new-object-as-prop */}
              </Tooltip.Trigger>
              <Tooltip.Portal>
                <Tooltip.Positioner>
                  <Tooltip.Popup>
                    {date}
                    <br />
                    <span className="text-success">{upPercent.toFixed(2)}% </span>
                    up
                    <br />
                    <span className="text-danger">{downPercent.toFixed(2)}%</span> down
                    <br />
                    <span aria-hidden className="text-muted-foreground">
                      [<span className="mx-1">{formatNumber(rollup.total_checks)} checks</span>]
                    </span>
                  </Tooltip.Popup>
                </Tooltip.Positioner>
              </Tooltip.Portal>
            </Tooltip.Root>
          );
        })}
      </div>
    </Tooltip.Provider>
  );
}
