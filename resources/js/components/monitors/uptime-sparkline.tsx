import { Tooltip } from '@/components/ui/tooltip';
import { cn } from '@/lib/cn';
import type { DailyUptimeRollup } from '@/types';

interface UptimeSparklineProps {
  data: DailyUptimeRollup[];
  days?: number;
  height?: number;
  className?: string;
}

function getBarStyle(upPercent: number, height: number) {
  const downPercent = 100 - upPercent;

  if (downPercent === 0) {
    // 100% uptime - full green
    return { upHeight: '100%', downHeight: '0%' };
  }

  // Minimum visible height for failures (at least 3px or 15% of bar, whichever is larger)
  const minDownPx = Math.max(3, height * 0.15);
  const minDownPercent = (minDownPx / height) * 100;

  // Scale the down portion to be more visible
  // Use a sqrt scale to emphasize small failures while not overwhelming large ones
  const scaledDownPercent = Math.max(minDownPercent, Math.sqrt(downPercent) * 10);

  // Cap at actual percentage or 50%, whichever is larger (don't underrepresent major outages)
  const finalDownPercent = Math.min(Math.max(scaledDownPercent, downPercent), 100);
  const finalUpPercent = 100 - finalDownPercent;

  return {
    upHeight: `${finalUpPercent}%`,
    downHeight: `${finalDownPercent}%`,
  };
}

export function UptimeSparkline({ data, days = 30, height = 24, className }: UptimeSparklineProps) {
  const rollupMap = new Map(data.map((r) => [r.date.slice(0, 10), r]));

  const dates: string[] = [];

  for (let i = days - 1; i >= 0; i--) {
    const date = new Date();

    date.setDate(date.getDate() - i);
    dates.push(date.toISOString().slice(0, 10));
  }

  return (
    <Tooltip.Provider>
      <div
        className={cn('flex items-end gap-px', className)}
        style={{ height }}
        role="img"
        aria-label={`Uptime over the last ${days} days`}
      >
        {dates.map((date) => {
          const rollup = rollupMap.get(date);

          if (!rollup || rollup.total_checks === 0) {
            // No data for this day - muted bar
            return (
              <Tooltip.Root key={date}>
                <Tooltip.Trigger
                  className="min-w-0.5 flex-1 bg-muted hover:bg-muted-foreground"
                  style={{ height: '100%' }}
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
                style={{ height: '100%' }}
              >
                <span className="sr-only">
                  {date}: {upPercent.toFixed(2)}% up
                </span>
                {downPercent > 0 ? (
                  <div className="w-full bg-danger" style={{ height: downHeight }} />
                ) : null}
                {upPercent > 0 ? (
                  <div className="w-full bg-success" style={{ height: upHeight }} />
                ) : null}
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
                      [<span className="mx-1">{rollup.total_checks} checks</span>]
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
