import { bucketDuration } from '@/lib/format/bucket';

export interface IntervalParts {
  value: number;
  unit: string;
  formatted: string;
}

export function formatInterval(seconds: number, verbose = false): IntervalParts {
  const bucket = bucketDuration(seconds * 1000, { includeSeconds: true, includeDays: false });
  const { value } = bucket;
  const verboseUnit = bucket.unit === 's' ? 'second' : bucket.unit === 'm' ? 'minute' : 'hour';
  const unit = verbose ? `${verboseUnit}${value !== 1 ? 's' : ''}` : bucket.unit;

  return {
    value,
    unit,
    formatted: `${value}${verbose ? ' ' : ''}${unit}`,
  };
}
