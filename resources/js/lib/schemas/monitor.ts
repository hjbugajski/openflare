import { z } from 'zod';

import type { HttpMethod } from '@/types';

export const INTERVAL_LABELS: Record<number, string> = {
  60: '1 minute',
  300: '5 minutes',
  900: '15 minutes',
  1800: '30 minutes',
  3600: '1 hour',
  10800: '3 hours',
  21600: '6 hours',
  43200: '12 hours',
  86400: '24 hours',
};

const HTTP_METHODS: [HttpMethod, ...HttpMethod[]] = ['GET', 'HEAD'];

export { HTTP_METHODS };

export const monitorSchema = z.object({
  name: z.string().min(1, 'name is required'),
  url: z.url('invalid url'),
  method: z.enum(HTTP_METHODS),
  interval: z.number().min(60, 'interval must be at least 60 seconds'),
  timeout: z
    .number()
    .min(5, 'timeout must be at least 5 seconds')
    .max(120, 'timeout cannot exceed 120 seconds'),
  expected_status_code: z
    .number()
    .min(100, 'status code must be between 100 and 599')
    .max(599, 'status code must be between 100 and 599'),
  is_active: z.boolean(),
  notifiers: z.array(z.string()),
});

export type MonitorFormValues = z.infer<typeof monitorSchema>;
