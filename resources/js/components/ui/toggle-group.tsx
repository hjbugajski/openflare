import { Toggle } from '@base-ui/react/toggle';
import { ToggleGroup as BaseToggleGroup } from '@base-ui/react/toggle-group';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/cn';

type RootProps = ComponentProps<typeof BaseToggleGroup>;

function Root({ className, ...props }: RootProps) {
  return (
    <BaseToggleGroup
      className={cn(
        'flex h-8 items-center gap-0.5 border border-border bg-background p-0.5',
        className,
      )}
      {...props}
    />
  );
}

type ItemProps = ComponentProps<typeof Toggle>;

function Item({ className, ...props }: ItemProps) {
  return (
    <Toggle
      className={cn(
        'flex aspect-square h-full w-full items-center justify-center bg-background text-muted-foreground transition outline-none',
        'hover:bg-muted hover:text-foreground',
        'focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-1 focus-visible:ring-offset-background',
        'data-pressed:bg-muted data-pressed:text-foreground',
        'disabled:pointer-events-none disabled:opacity-40',
        className,
      )}
      {...props}
    />
  );
}

export const ToggleGroup = { Root, Item };
