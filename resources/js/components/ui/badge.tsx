import type { HTMLAttributes } from 'react';

import { type VariantProps, cva } from 'class-variance-authority';

import { cn } from '@/lib/cn';

const badgeVariants = cva('font-medium uppercase', {
  variants: {
    variant: {
      default: 'text-foreground',
      secondary: 'text-muted-foreground',
      accent: 'text-accent',
      success: 'text-success',
      warning: 'text-warning',
      danger: 'text-danger',
      info: 'text-info',
      red: 'text-red-600 dark:text-red-400',
      orange: 'text-orange-600 dark:text-orange-400',
      yellow: 'text-yellow-600 dark:text-yellow-400',
      green: 'text-green-600 dark:text-green-400',
      cyan: 'text-cyan-600 dark:text-cyan-400',
      blue: 'text-blue-600 dark:text-blue-400',
      purple: 'text-purple-600 dark:text-purple-400',
      magenta: 'text-magenta-600 dark:text-magenta-400',
    },
    size: {
      sm: 'text-xs',
      md: 'text-sm',
      lg: 'text-base',
    },
  },
  defaultVariants: {
    variant: 'default',
    size: 'md',
  },
});

type BadgeProps = HTMLAttributes<HTMLSpanElement> & VariantProps<typeof badgeVariants>;

function Badge({ children, className, size, variant, ...props }: BadgeProps) {
  return (
    <span className={cn(badgeVariants({ size, variant }), className)} {...props}>
      <span aria-hidden>[</span>
      <span className="mx-1">{children}</span>
      <span aria-hidden>]</span>
    </span>
  );
}

export { Badge };
