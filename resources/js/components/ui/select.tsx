import type { ComponentProps } from 'react';

import { Select as BaseSelect } from '@base-ui/react/select';

import { cn } from '@/lib/cn';

function Root(props: ComponentProps<typeof BaseSelect.Root>) {
  return <BaseSelect.Root {...props} />;
}

function Trigger({ className, ...props }: ComponentProps<typeof BaseSelect.Trigger>) {
  return (
    <BaseSelect.Trigger
      className={cn(
        'flex h-9 w-full items-center justify-between border border-border bg-background py-2 pr-2 pl-3 text-sm text-foreground transition outline-none',
        'focus-visible:border-muted-foreground focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-1 focus-visible:ring-offset-background',
        'data-disabled:cursor-not-allowed data-disabled:opacity-50',
        className,
      )}
      {...props}
    />
  );
}

function Value(props: ComponentProps<typeof BaseSelect.Value>) {
  return <BaseSelect.Value {...props} />;
}

function Icon({ className, ...props }: ComponentProps<typeof BaseSelect.Icon>) {
  return <BaseSelect.Icon className={cn('text-muted-foreground', className)} {...props} />;
}

function Portal(props: ComponentProps<typeof BaseSelect.Portal>) {
  return <BaseSelect.Portal {...props} />;
}

function Positioner({ className, ...props }: ComponentProps<typeof BaseSelect.Positioner>) {
  return <BaseSelect.Positioner className={cn('z-50 outline-none', className)} {...props} />;
}

function Popup({ className, ...props }: ComponentProps<typeof BaseSelect.Popup>) {
  return (
    <BaseSelect.Popup
      className={cn(
        'group max-h-(--available-height) origin-(--transform-origin) overflow-y-auto bg-background text-sm outline outline-border',
        className,
      )}
      {...props}
    />
  );
}

function Item({ className, ...props }: ComponentProps<typeof BaseSelect.Item>) {
  return (
    <BaseSelect.Item
      className={cn(
        'flex min-w-(--anchor-width) items-center px-3 py-2 text-foreground outline-none group-data-[side=none]:min-w-[calc(var(--anchor-width))]',
        'data-highlighted:bg-muted data-selected:text-accent',
        className,
      )}
      {...props}
    />
  );
}

function ItemText({ className, ...props }: ComponentProps<typeof BaseSelect.ItemText>) {
  return <BaseSelect.ItemText className={cn('', className)} {...props} />;
}

function Separator({ className, ...props }: ComponentProps<typeof BaseSelect.Separator>) {
  return <BaseSelect.Separator className={cn('h-px bg-border', className)} {...props} />;
}

export const Select = {
  Root,
  Trigger,
  Value,
  Icon,
  Portal,
  Positioner,
  Popup,
  Item,
  ItemText,
  Separator,
};
