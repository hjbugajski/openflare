export interface DurationParts {
  value: number | string;
  unit: string;
  suffix?: string;
}

export function formatDurationParts(start: string, end: string | null): DurationParts {
  const startTime = new Date(start).getTime();
  const endTime = end ? new Date(end).getTime() : Date.now();
  const diff = endTime - startTime;

  const minutes = Math.floor(diff / 60000);
  const hours = Math.floor(minutes / 60);
  const days = Math.floor(hours / 24);

  if (days > 0) return { value: days, unit: 'd', suffix: `${hours % 24}h` };
  if (hours > 0) return { value: hours, unit: 'h', suffix: `${minutes % 60}m` };
  if (minutes > 0) return { value: minutes, unit: 'm' };
  return { value: '<1', unit: 'm' };
}

export function formatDuration(start: string, end: string | null): string {
  const duration = formatDurationParts(start, end);
  const suffix = duration.suffix ? ` ${duration.suffix}` : '';

  return `${duration.value}${duration.unit}${suffix}`;
}
