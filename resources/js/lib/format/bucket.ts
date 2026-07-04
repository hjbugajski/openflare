export interface BucketResult {
  value: number;
  unit: 'd' | 'h' | 'm' | 's';
  remainder?: number;
  remainderUnit?: 'h' | 'm';
}

interface BucketOptions {
  includeDays?: boolean;
  includeSeconds?: boolean;
}

const MINUTE = 60;
const HOUR = 3600;
const DAY = 86400;

/** Buckets a duration (in ms) into the largest applicable unit, with an optional remainder in the next-smaller unit. */
export function bucketDuration(ms: number, options: BucketOptions = {}): BucketResult {
  const { includeDays = true, includeSeconds = false } = options;
  const totalSeconds = Math.max(0, Math.floor(ms / 1000));

  if (includeSeconds && totalSeconds < MINUTE) {
    return { value: totalSeconds, unit: 's' };
  }

  if (totalSeconds < HOUR) {
    return { value: Math.floor(totalSeconds / MINUTE), unit: 'm' };
  }

  const totalHours = Math.floor(totalSeconds / HOUR);

  if (!includeDays || totalSeconds < DAY) {
    const remainder = Math.floor(totalSeconds / MINUTE) % 60;
    return remainder > 0
      ? { value: totalHours, unit: 'h', remainder, remainderUnit: 'm' }
      : { value: totalHours, unit: 'h' };
  }

  const totalDays = Math.floor(totalSeconds / DAY);
  const remainder = totalHours % 24;

  return remainder > 0
    ? { value: totalDays, unit: 'd', remainder, remainderUnit: 'h' }
    : { value: totalDays, unit: 'd' };
}
