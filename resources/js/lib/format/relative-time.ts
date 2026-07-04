import { bucketDuration } from '@/lib/format/bucket';

export interface RelativeTimeParts {
  value: number;
  unit: string;
  suffix: string;
  formatted: string;
}

interface FormatRelativeTimeOptions {
  format?: 'parts';
}

export function formatRelativeTime(date: string | null): string;
export function formatRelativeTime(
  date: string | null,
  options: FormatRelativeTimeOptions,
): RelativeTimeParts | null;
export function formatRelativeTime(
  date: string | null,
  options?: FormatRelativeTimeOptions,
): string | RelativeTimeParts | null {
  if (!date) {
    return options?.format ? null : 'never';
  }

  const diff = Date.now() - new Date(date).getTime();
  const seconds = Math.floor(diff / 1000);

  if (seconds < 60) {
    return options?.format ? null : 'just now';
  }

  const { value, unit } = bucketDuration(diff, { includeDays: true });

  const formatted = `${value}${unit} ago`;
  const parts = {
    value,
    unit,
    suffix: 'ago',
    formatted,
  };

  if (options?.format) {
    return parts;
  }

  return formatted;
}
