import type { ComponentProps } from 'react';

import { cn } from '@/lib/cn';

function Input({ className, type, ...props }: ComponentProps<'input'>) {
  return (
    <input
      type={type}
      className={cn(
        'flex h-9 w-full border border-border bg-background px-3 py-2 text-sm text-foreground transition outline-none',
        'placeholder:text-muted-foreground',
        'focus-visible:border-muted-foreground focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-1 focus-visible:ring-offset-background',
        'disabled:cursor-not-allowed disabled:opacity-50',
        className,
      )}
      {...props}
    />
  );
}

export { Input };
