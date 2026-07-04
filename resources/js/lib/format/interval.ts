import { bucketDuration } from '@/lib/format/bucket';

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
  let verbose: boolean;
  let format: 'parts' | 'string';

  if (typeof options === 'boolean' || options === undefined) {
    verbose = typeof options === 'boolean' ? options : false;
    format = 'parts';
  } else {
    verbose = options.verbose ?? false;
    format = options.format ?? 'parts';
  }

  const bucket = bucketDuration(seconds * 1000, { includeSeconds: true, includeDays: false });
  const { value } = bucket;
  const verboseUnit = bucket.unit === 's' ? 'second' : bucket.unit === 'm' ? 'minute' : 'hour';
  const unit = verbose ? `${verboseUnit}${value !== 1 ? 's' : ''}` : bucket.unit;

  const parts: IntervalParts = {
    value,
    unit,
    formatted: `${value}${verbose ? ' ' : ''}${unit}`,
  };

  if (format === 'string') {
    return parts.formatted;
  }

  return parts;
}
