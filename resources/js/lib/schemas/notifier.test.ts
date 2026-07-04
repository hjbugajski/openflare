import { describe, expect, it } from 'vitest';

import { notifierSchema } from '@/lib/schemas/notifier';

const base = {
  name: 'my notifier',
  is_active: true,
  is_default: false,
  monitors: [] as string[],
};

describe('notifierSchema', () => {
  it('accepts a valid discord config', () => {
    const result = notifierSchema.safeParse({
      ...base,
      type: 'discord',
      config: { webhook_url: 'https://discord.com/api/webhooks/123456/abcDEF-token_123' },
    });
    expect(result.success).toBe(true);
  });

  it('rejects a discord config with an invalid webhook URL', () => {
    const result = notifierSchema.safeParse({
      ...base,
      type: 'discord',
      config: { webhook_url: 'https://example.com/not-a-webhook' },
    });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues[0]?.path).toEqual(['config', 'webhook_url']);
    }
  });

  it('accepts a valid email config', () => {
    const result = notifierSchema.safeParse({
      ...base,
      type: 'email',
      config: { email: 'alerts@example.com' },
    });
    expect(result.success).toBe(true);
  });

  it('rejects an invalid email address', () => {
    const result = notifierSchema.safeParse({
      ...base,
      type: 'email',
      config: { email: 'not-an-email' },
    });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues[0]?.path).toEqual(['config', 'email']);
    }
  });

  it('rejects an empty name', () => {
    const result = notifierSchema.safeParse({
      ...base,
      name: '',
      type: 'email',
      config: { email: 'alerts@example.com' },
    });
    expect(result.success).toBe(false);
  });

  it('rejects an unknown type', () => {
    const result = notifierSchema.safeParse({
      ...base,
      type: 'sms',
      config: {},
    });
    expect(result.success).toBe(false);
  });
});
