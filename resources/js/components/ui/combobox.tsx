import { Combobox as BaseCombobox } from '@base-ui/react/combobox';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/cn';

function Root<T, Multiple extends boolean | undefined = false>(
  props: ComponentProps<typeof BaseCombobox.Root<T, Multiple>>,
) {
  return <BaseCombobox.Root {...props} />;
}

function Input({ className, ...props }: ComponentProps<typeof BaseCombobox.Input>) {
  return (
    <BaseCombobox.Input
      className={cn(
        'h-6 min-w-16 flex-1 border-0 bg-transparent text-sm text-foreground outline-none placeholder:text-muted-foreground',
        className,
      )}
      {...props}
    />
  );
}

function Trigger({ className, ...props }: ComponentProps<typeof BaseCombobox.Trigger>) {
  return <BaseCombobox.Trigger className={className} {...props} />;
}

function Clear({ className, ...props }: ComponentProps<typeof BaseCombobox.Clear>) {
  return <BaseCombobox.Clear className={className} {...props} />;
}

function Value(props: ComponentProps<typeof BaseCombobox.Value>) {
  return <BaseCombobox.Value {...props} />;
}

function Icon({ className, ...props }: ComponentProps<typeof BaseCombobox.Icon>) {
  return <BaseCombobox.Icon className={cn('text-muted-foreground', className)} {...props} />;
}

function Chips({ className, ...props }: ComponentProps<typeof BaseCombobox.Chips>) {
  return (
    <BaseCombobox.Chips
      className={cn(
        'flex h-9 w-full flex-wrap items-center gap-1 border border-border bg-background px-3 text-sm text-foreground transition outline-none',
        'focus-within:border-muted-foreground focus-within:ring-2 focus-within:ring-accent focus-within:ring-offset-1 focus-within:ring-offset-background',
        'data-disabled:cursor-not-allowed data-disabled:opacity-50',
        className,
      )}
      {...props}
    />
  );
}

function Chip({ className, ...props }: ComponentProps<typeof BaseCombobox.Chip>) {
  return (
    <BaseCombobox.Chip
      className={cn(
        'flex cursor-default items-center gap-1 bg-muted px-1.5 py-0.5 text-xs text-foreground outline-none data-highlighted:bg-accent data-highlighted:text-accent-foreground',
        className,
      )}
      {...props}
    />
  );
}

function ChipRemove({ className, ...props }: ComponentProps<typeof BaseCombobox.ChipRemove>) {
  return (
    <BaseCombobox.ChipRemove
      className={cn('ml-0.5 p-0.5 text-muted-foreground hover:text-foreground', className)}
      {...props}
    />
  );
}

function Portal(props: ComponentProps<typeof BaseCombobox.Portal>) {
  return <BaseCombobox.Portal {...props} />;
}

function Positioner({ className, ...props }: ComponentProps<typeof BaseCombobox.Positioner>) {
  return <BaseCombobox.Positioner className={cn('z-50 outline-none', className)} {...props} />;
}

function Popup({ className, ...props }: ComponentProps<typeof BaseCombobox.Popup>) {
  return (
    <BaseCombobox.Popup
      className={cn(
        'max-h-(--available-height) w-(--anchor-width) max-w-(--available-width) origin-(--transform-origin) overflow-y-auto bg-background text-sm outline outline-border',
        'transition-[transform,opacity] duration-100',
        'data-ending-style:scale-95 data-ending-style:opacity-0',
        'data-starting-style:scale-95 data-starting-style:opacity-0',
        className,
      )}
      {...props}
    />
  );
}

function List(props: ComponentProps<typeof BaseCombobox.List>) {
  return <BaseCombobox.List {...props} />;
}

function Item({ className, ...props }: ComponentProps<typeof BaseCombobox.Item>) {
  return (
    <BaseCombobox.Item
      className={cn(
        'flex min-w-(--anchor-width) cursor-default items-center justify-between px-3 py-2 text-foreground outline-none select-none',
        'data-highlighted:bg-muted data-selected:text-accent',
        className,
      )}
      {...props}
    />
  );
}

function Empty({ className, ...props }: ComponentProps<typeof BaseCombobox.Empty>) {
  return (
    <BaseCombobox.Empty
      className={cn('px-3 py-2 text-muted-foreground empty:m-0 empty:p-0', className)}
      {...props}
    />
  );
}

function Group(props: ComponentProps<typeof BaseCombobox.Group>) {
  return <BaseCombobox.Group {...props} />;
}

function GroupLabel({ className, ...props }: ComponentProps<typeof BaseCombobox.GroupLabel>) {
  return <BaseCombobox.GroupLabel className={className} {...props} />;
}

function Arrow({ className, ...props }: ComponentProps<typeof BaseCombobox.Arrow>) {
  return <BaseCombobox.Arrow className={className} {...props} />;
}

export const Combobox = {
  Root,
  Input,
  Trigger,
  Clear,
  Value,
  Icon,
  Chips,
  Chip,
  ChipRemove,
  Portal,
  Positioner,
  Popup,
  List,
  Item,
  Empty,
  Group,
  GroupLabel,
  Arrow,
};
