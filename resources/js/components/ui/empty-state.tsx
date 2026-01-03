import type { ComponentProps } from 'react';

import { cn } from '@/lib/cn';

interface EmptyStateProps extends ComponentProps<'p'> {
  message: string;
}

export function EmptyState({ className, message }: EmptyStateProps) {
  return (
    <p className={cn('pb-8 text-center text-muted-foreground', className)}>
      <span aria-hidden className="text-accent">
        [
      </span>
      <span className="mx-1">{message}</span>
      <span aria-hidden className="text-accent">
        ]
      </span>
    </p>
  );
}
