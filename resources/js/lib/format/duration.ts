export interface DurationParts {
  value: number | string;
  unit: string;
  suffixValue?: number;
  suffixUnit?: string;
}

export function formatDurationParts(start: string, end: string | null): DurationParts {
  const startTime = new Date(start).getTime();
  const endTime = end ? new Date(end).getTime() : Date.now();
  const diff = endTime - startTime;

  const minutes = Math.floor(diff / 60000);
  const hours = Math.floor(minutes / 60);
  const days = Math.floor(hours / 24);

  if (days > 0) {
    const remainingHours = hours % 24;
    return remainingHours > 0
      ? { value: days, unit: 'd', suffixValue: remainingHours, suffixUnit: 'h' }
      : { value: days, unit: 'd' };
  }
  if (hours > 0) {
    const remainingMinutes = minutes % 60;
    return remainingMinutes > 0
      ? { value: hours, unit: 'h', suffixValue: remainingMinutes, suffixUnit: 'm' }
      : { value: hours, unit: 'h' };
  }
  if (minutes > 0) {
    return { value: minutes, unit: 'm' };
  }
  return { value: '<1', unit: 'm' };
}

export function formatDuration(start: string, end: string | null): string {
  const duration = formatDurationParts(start, end);
  const suffix =
    duration.suffixValue !== undefined ? ` ${duration.suffixValue}${duration.suffixUnit}` : '';

  return `${duration.value}${duration.unit}${suffix}`;
}
