import { describe, expect, it } from 'vitest';

import { configForType, notifierSchema } from '@/lib/schemas/notifier';

const base = {
  name: 'my notifier',
  is_active: true,
  is_default: false,
  monitors: [] as string[],
};

describe('notifierSchema', () => {
  it('accepts a valid discord config', () => {
    const result = notifierSchema().safeParse({
      ...base,
      type: 'discord',
      config: { webhook_url: 'https://discord.com/api/webhooks/123456/abcDEF-token_123' },
    });
    expect(result.success).toBe(true);
  });

  it('rejects a discord config with an invalid webhook URL', () => {
    const result = notifierSchema().safeParse({
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
    const result = notifierSchema().safeParse({
      ...base,
      type: 'email',
      config: { email: 'alerts@example.com' },
    });
    expect(result.success).toBe(true);
  });

  it('rejects an invalid email address', () => {
    const result = notifierSchema().safeParse({
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
    const result = notifierSchema().safeParse({
      ...base,
      name: '',
      type: 'email',
      config: { email: 'alerts@example.com' },
    });
    expect(result.success).toBe(false);
  });

  it('rejects an unknown type', () => {
    const result = notifierSchema().safeParse({
      ...base,
      type: 'sms',
      config: {},
    });
    expect(result.success).toBe(false);
  });

  it('rejects an empty discord webhook URL', () => {
    const result = notifierSchema().safeParse({
      ...base,
      type: 'discord',
      config: { webhook_url: '' },
    });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues[0]?.path).toEqual(['config', 'webhook_url']);
    }
  });
});

describe('configForType', () => {
  it('keeps only the active type key', () => {
    expect(
      configForType('email', { webhook_url: 'https://discord.com/x', email: 'a@b.com' }),
    ).toEqual({ email: 'a@b.com' });
  });

  it('drops a blank value so the stored one is kept', () => {
    expect(configForType('discord', { webhook_url: '', email: 'a@b.com' })).toEqual({});
  });
});

describe('notifierSchema with a stored webhook URL', () => {
  const editSchema = notifierSchema({ keepsStoredWebhookUrl: true });

  it('accepts an empty discord webhook URL', () => {
    const result = editSchema.safeParse({
      ...base,
      type: 'discord',
      config: { webhook_url: '' },
    });
    expect(result.success).toBe(true);
  });

  it('still rejects an invalid discord webhook URL', () => {
    const result = editSchema.safeParse({
      ...base,
      type: 'discord',
      config: { webhook_url: 'https://example.com/not-a-webhook' },
    });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues[0]?.path).toEqual(['config', 'webhook_url']);
    }
  });

  it('still requires an email address after switching to the email type', () => {
    const result = editSchema.safeParse({
      ...base,
      type: 'email',
      config: { webhook_url: '', email: '' },
    });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues[0]?.path).toEqual(['config', 'email']);
    }
  });
});
