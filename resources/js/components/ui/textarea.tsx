import type { ComponentProps } from 'react';

import { cn } from '@/lib/cn';

function Textarea({ className, ...props }: Omit<ComponentProps<'textarea'>, 'size'>) {
  return (
    <textarea
      className={cn(
        'flex min-h-18 w-full resize-y border border-border bg-background px-3 py-2 text-sm text-foreground transition-colors',
        'placeholder:text-muted-foreground',
        'focus-visible:border-accent focus-visible:outline-none',
        'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
        'aria-invalid:border-destructive',
        className,
      )}
      {...props}
    />
  );
}

export { Textarea };
