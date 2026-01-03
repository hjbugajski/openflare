import { cn } from '@/lib/cn';
import type { DailyUptimeRollup } from '@/types';

function calculateOverallUptime(data: DailyUptimeRollup[]): number | null {
  if (data.length === 0) return null;

  const totalChecks = data.reduce((sum, r) => sum + r.total_checks, 0);
  const successfulChecks = data.reduce((sum, r) => sum + r.successful_checks, 0);

  if (totalChecks === 0) return null;
  return (successfulChecks / totalChecks) * 100;
}

interface UptimePercentageProps {
  data: DailyUptimeRollup[];
  className?: string;
}

export function UptimePercentage({ data, className }: UptimePercentageProps) {
  const percentage = calculateOverallUptime(data);

  if (percentage === null) {
    return <span className={cn('text-muted-foreground', className)}>--.--%</span>;
  }

  const colorClass =
    percentage >= 100 ? 'text-success' : percentage >= 99 ? 'text-warning' : 'text-danger';

  return <span className={cn('font-medium', colorClass, className)}>{percentage.toFixed(2)}%</span>;
}
