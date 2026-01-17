import { Checkbox as BaseCheckbox } from '@base-ui/react/checkbox';
import type { ComponentProps } from 'react';

import { IconCheck } from '@/components/icons/check';
import { cn } from '@/lib/cn';

function Root({ className, ...props }: ComponentProps<typeof BaseCheckbox.Root>) {
  return (
    <BaseCheckbox.Root
      className={cn(
        'group flex size-4 shrink-0 items-center justify-center border border-border bg-background transition outline-none',
        'data-checked:bg-accent',
        'focus-visible:ring-2 focus-visible:ring-accent',
        'data-disabled:cursor-not-allowed data-disabled:opacity-50',
        className,
      )}
      {...props}
    />
  );
}

function Indicator({ className, ...props }: ComponentProps<typeof BaseCheckbox.Indicator>) {
  return (
    <BaseCheckbox.Indicator className={cn('text-accent-foreground', className)} {...props}>
      <IconCheck className="size-4" />
    </BaseCheckbox.Indicator>
  );
}

export const Checkbox = {
  Root,
  Indicator,
};
