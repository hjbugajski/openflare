import { describe, expect, it } from 'vitest';

import { formatInterval } from '@/lib/format/interval';

describe('formatInterval', () => {
  it('formats seconds under a minute (parts)', () => {
    expect(formatInterval(45)).toEqual({ value: 45, unit: 's', formatted: '45s' });
  });

  it('formats seconds under a minute (verbose)', () => {
    expect(formatInterval(45, true)).toEqual({
      value: 45,
      unit: 'seconds',
      formatted: '45 seconds',
    });
  });

  it('formats singular verbose second', () => {
    expect(formatInterval(1, true)).toEqual({ value: 1, unit: 'second', formatted: '1 second' });
  });

  it('formats minutes (parts)', () => {
    expect(formatInterval(90)).toEqual({ value: 1, unit: 'm', formatted: '1m' });
  });

  it('formats minutes as a string', () => {
    expect(formatInterval(90, { format: 'string' })).toBe('1m');
  });

  it('formats hours, verbose, as a string', () => {
    expect(formatInterval(3661, { verbose: true, format: 'string' })).toBe('1 hour');
  });
});
