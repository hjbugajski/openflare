import { bucketDuration } from '@/lib/format/bucket';

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

  const bucket = bucketDuration(diff, { includeDays: true });

  if (bucket.unit === 'm' && bucket.value === 0) {
    return { value: '<1', unit: 'm' };
  }

  return bucket.remainder !== undefined
    ? {
        value: bucket.value,
        unit: bucket.unit,
        suffixValue: bucket.remainder,
        suffixUnit: bucket.remainderUnit,
      }
    : { value: bucket.value, unit: bucket.unit };
}

export function formatDuration(start: string, end: string | null): string {
  const duration = formatDurationParts(start, end);
  const suffix =
    duration.suffixValue !== undefined ? ` ${duration.suffixValue}${duration.suffixUnit}` : '';

  return `${duration.value}${duration.unit}${suffix}`;
}
