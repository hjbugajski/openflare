import { z } from 'zod';

import type { NotifierType } from '@/types';

const NOTIFIER_TYPES: [NotifierType, ...NotifierType[]] = ['discord', 'email'];

const DISCORD_WEBHOOK_REGEX = /^https:\/\/discord\.com\/api\/webhooks\/\d+\/[\w-]+$/;

export { NOTIFIER_TYPES };

export const NOTIFIER_TYPE_LABELS: Record<NotifierType, string> = {
  discord: 'discord',
  email: 'email',
};

export const NOTIFIER_TYPE_DESCRIPTIONS: Record<NotifierType, string> = {
  discord: 'send notifications to a Discord channel via webhook',
  email: 'send notifications to an email address',
};

export const notifierConfigSchema = z.object({
  webhook_url: z.string().optional(),
  email: z.string().optional(),
});

export type NotifierConfig = z.infer<typeof notifierConfigSchema>;

/**
 * Validates notifier config based on type.
 * Used by both form submission and test notification.
 */
export function validateNotifierConfig(
  type: NotifierType | string,
  config: NotifierConfig,
): { valid: true } | { valid: false; message: string } {
  if (type === 'discord') {
    if (!config.webhook_url) {
      return { valid: false, message: 'webhook URL is required' };
    }
    try {
      new URL(config.webhook_url);
    } catch {
      return { valid: false, message: 'invalid webhook URL' };
    }
    if (!DISCORD_WEBHOOK_REGEX.test(config.webhook_url)) {
      return { valid: false, message: 'URL must be a valid Discord webhook URL' };
    }
  }

  if (type === 'email') {
    if (!config.email) {
      return { valid: false, message: 'email address is required' };
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(config.email)) {
      return { valid: false, message: 'invalid email address' };
    }
  }

  return { valid: true };
}

export const notifierSchema = z
  .object({
    name: z.string().min(1, 'name is required'),
    type: z.enum(NOTIFIER_TYPES, { message: 'type is required' }),
    config: notifierConfigSchema,
    is_active: z.boolean(),
    is_default: z.boolean(),
    apply_to_existing: z.boolean().optional(),
    monitors: z.array(z.string()),
    excluded_monitors: z.array(z.string()).optional(),
  })
  .superRefine((data, ctx) => {
    const result = validateNotifierConfig(data.type, data.config);
    if (!result.valid) {
      const path = data.type === 'discord' ? ['config', 'webhook_url'] : ['config', 'email'];
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: result.message,
        path,
      });
    }
  });

export type NotifierFormValues = z.infer<typeof notifierSchema>;
