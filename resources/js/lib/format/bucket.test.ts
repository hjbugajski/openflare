import { describe, expect, it } from 'vitest';

import { bucketDuration } from '@/lib/format/bucket';

describe('bucketDuration', () => {
  it('buckets under a minute as minutes by default', () => {
    expect(bucketDuration(45 * 1000)).toEqual({ value: 0, unit: 'm' });
  });

  it('buckets seconds when includeSeconds is set', () => {
    expect(bucketDuration(45 * 1000, { includeSeconds: true })).toEqual({
      value: 45,
      unit: 's',
    });
  });

  it('buckets minutes', () => {
    expect(bucketDuration(30 * 60 * 1000)).toEqual({ value: 30, unit: 'm' });
  });

  it('buckets hours with a minute remainder', () => {
    expect(bucketDuration(90 * 60 * 1000)).toEqual({
      value: 1,
      unit: 'h',
      remainder: 30,
      remainderUnit: 'm',
    });
  });

  it('buckets hours without a remainder', () => {
    expect(bucketDuration(2 * 3600 * 1000)).toEqual({ value: 2, unit: 'h' });
  });

  it('rolls hours into days with an hour remainder by default', () => {
    expect(bucketDuration(25 * 3600 * 1000)).toEqual({
      value: 1,
      unit: 'd',
      remainder: 1,
      remainderUnit: 'h',
    });
  });

  it('does not roll into days when includeDays is false', () => {
    expect(bucketDuration(90 * 3600 * 1000, { includeDays: false })).toEqual({
      value: 90,
      unit: 'h',
    });
  });

  it('buckets exact days without a remainder', () => {
    expect(bucketDuration(3 * 86400 * 1000)).toEqual({ value: 3, unit: 'd' });
  });

  it('buckets 0ms as 0 minutes by default', () => {
    expect(bucketDuration(0)).toEqual({ value: 0, unit: 'm' });
  });

  it('buckets 0ms as 0 seconds when includeSeconds is set', () => {
    expect(bucketDuration(0, { includeSeconds: true })).toEqual({ value: 0, unit: 's' });
  });

  it('rolls the exact minute boundary into minutes, not seconds', () => {
    expect(bucketDuration(60_000, { includeSeconds: true })).toEqual({ value: 1, unit: 'm' });
  });

  it('rolls the exact hour boundary into hours with no remainder', () => {
    expect(bucketDuration(3_600_000)).toEqual({ value: 1, unit: 'h' });
  });

  it('rolls the exact day boundary into days with no remainder', () => {
    expect(bucketDuration(86_400_000)).toEqual({ value: 1, unit: 'd' });
  });

  it('keeps the exact day boundary in hours when includeDays is false', () => {
    expect(bucketDuration(86_400_000, { includeDays: false })).toEqual({ value: 24, unit: 'h' });
  });

  it('clamps negative durations to zero', () => {
    expect(bucketDuration(-1000)).toEqual({ value: 0, unit: 'm' });
    expect(bucketDuration(-1000, { includeSeconds: true })).toEqual({ value: 0, unit: 's' });
  });
});
