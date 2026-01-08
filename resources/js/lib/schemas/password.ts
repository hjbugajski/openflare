import { z } from 'zod';

export const passwordSchema = z
  .string()
  .min(12, 'password must be at least 12 characters')
  .regex(/[a-z]/, 'password must contain a lowercase letter')
  .regex(/[A-Z]/, 'password must contain an uppercase letter')
  .regex(/[0-9]/, 'password must contain a number')
  .regex(/[^a-zA-Z0-9]/, 'password must contain a symbol');
