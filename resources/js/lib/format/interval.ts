export interface IntervalParts {
  value: number;
  unit: string;
  formatted: string;
}

interface FormatIntervalOptions {
  verbose?: boolean;
  format?: 'parts' | 'string';
}

export function formatInterval(seconds: number, verbose?: boolean): IntervalParts;
export function formatInterval(
  seconds: number,
  options: FormatIntervalOptions & { format: 'string' },
): string;
export function formatInterval(
  seconds: number,
  options?: FormatIntervalOptions | boolean,
): IntervalParts | string {
  const normalizedOptions =
    typeof options === 'boolean'
      ? { verbose: options, format: 'parts' }
      : { verbose: false, format: 'parts', ...options };
  const { verbose, format } = normalizedOptions;

  let parts: IntervalParts;
  if (seconds < 60) {
    const unit = verbose ? `second${seconds !== 1 ? 's' : ''}` : 's';
    parts = {
      value: seconds,
      unit,
      formatted: `${seconds}${verbose ? ' ' : ''}${unit}`,
    };
  } else if (seconds < 3600) {
    const minutes = Math.floor(seconds / 60);
    const unit = verbose ? `minute${minutes !== 1 ? 's' : ''}` : 'm';
    parts = {
      value: minutes,
      unit,
      formatted: `${minutes}${verbose ? ' ' : ''}${unit}`,
    };
  } else {
    const hours = Math.floor(seconds / 3600);
    const unit = verbose ? `hour${hours !== 1 ? 's' : ''}` : 'h';
    parts = {
      value: hours,
      unit,
      formatted: `${hours}${verbose ? ' ' : ''}${unit}`,
    };
  }

  if (format === 'string') {
    return parts.formatted;
  }

  return parts;
}
