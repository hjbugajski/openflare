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
  if (!date) return options?.format ? null : 'never';

  const diff = Date.now() - new Date(date).getTime();
  const seconds = Math.floor(diff / 1000);

  if (seconds < 60) {
    return options?.format ? null : 'just now';
  }

  let value: number;
  let unit: string;

  if (seconds < 3600) {
    value = Math.floor(seconds / 60);
    unit = 'm';
  } else if (seconds < 86400) {
    value = Math.floor(seconds / 3600);
    unit = 'h';
  } else {
    value = Math.floor(seconds / 86400);
    unit = 'd';
  }

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
