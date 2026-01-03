import type { ComponentProps } from 'react';

import { cn } from '@/lib/cn';

function Label({ className, children, ...props }: ComponentProps<'label'>) {
  return (
    <label
      className={cn(
        'text-xs font-medium text-muted-foreground uppercase',
        'data-disabled:cursor-not-allowed data-disabled:opacity-50',
        className,
      )}
      {...props}
    >
      <span aria-hidden className="mr-1 text-accent">
        &gt;
      </span>
      {children}
    </label>
  );
}

function Description({ className, children, ...props }: ComponentProps<'p'>) {
  return (
    <p className={cn('text-xs text-muted-foreground', className)} {...props}>
      <span aria-hidden className="mr-1 text-muted-foreground">
        {'//'}
      </span>
      {children}
    </p>
  );
}

function ErrorMessage({ className, children, ...props }: ComponentProps<'p'>) {
  return (
    <p className={cn('text-xs text-danger', className)} role="alert" {...props}>
      <span aria-hidden className="mr-1">
        [!]
      </span>
      {children}
    </p>
  );
}

export { Label, Description, ErrorMessage };
