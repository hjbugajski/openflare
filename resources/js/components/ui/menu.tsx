import { type ComponentProps } from 'react';

import { Menu as BaseMenu } from '@base-ui/react/menu';

import { cn } from '@/lib/cn';

function Root(props: ComponentProps<typeof BaseMenu.Root>) {
  return <BaseMenu.Root {...props} />;
}

function Trigger(props: ComponentProps<typeof BaseMenu.Trigger>) {
  return <BaseMenu.Trigger {...props} />;
}

function Portal(props: ComponentProps<typeof BaseMenu.Portal>) {
  return <BaseMenu.Portal {...props} />;
}

function Positioner({ className, ...props }: ComponentProps<typeof BaseMenu.Positioner>) {
  return (
    <BaseMenu.Positioner className={cn('z-50 outline-none', className)} sideOffset={8} {...props} />
  );
}

function Popup({ className, ...props }: ComponentProps<typeof BaseMenu.Popup>) {
  return (
    <BaseMenu.Popup
      className={cn(
        'origin-var(--transform-origin) min-w-48 border border-border bg-background outline-none',
        'transition-[transform,scale,opacity] duration-150',
        'data-starting-style:scale-95 data-starting-style:opacity-0',
        'data-ending-style:scale-95 data-ending-style:opacity-0',
        className,
      )}
      {...props}
    />
  );
}

function Item({ className, ...props }: ComponentProps<typeof BaseMenu.Item>) {
  return (
    <BaseMenu.Item
      className={cn(
        'flex cursor-default items-center gap-2 px-4 py-2 text-sm text-muted-foreground outline-none select-none',
        'data-highlighted:bg-muted data-highlighted:text-foreground',
        className,
      )}
      {...props}
    />
  );
}

function Separator({ className, ...props }: ComponentProps<typeof BaseMenu.Separator>) {
  return <BaseMenu.Separator className={cn('border-t border-border', className)} {...props} />;
}

function RadioGroup(props: ComponentProps<typeof BaseMenu.RadioGroup>) {
  return <BaseMenu.RadioGroup {...props} />;
}

function RadioItem({ className, children, ...props }: ComponentProps<typeof BaseMenu.RadioItem>) {
  return (
    <BaseMenu.RadioItem
      className={cn(
        'group flex cursor-default items-center gap-2 px-4 py-2 text-sm text-muted-foreground outline-none select-none',
        'data-highlighted:bg-muted data-highlighted:text-foreground',
        className,
      )}
      {...props}
    >
      <span aria-hidden className="text-accent">
        <span className="group-data-checked:hidden">○</span>
        <span className="hidden group-data-checked:inline">●</span>
      </span>
      {children}
    </BaseMenu.RadioItem>
  );
}

function GroupLabel({ className, ...props }: ComponentProps<typeof BaseMenu.GroupLabel>) {
  return (
    <BaseMenu.GroupLabel
      className={cn('px-4 py-2 text-xs text-muted-foreground uppercase', className)}
      {...props}
    />
  );
}

function Group(props: ComponentProps<typeof BaseMenu.Group>) {
  return <BaseMenu.Group {...props} />;
}

export const Menu = {
  Root,
  Trigger,
  Portal,
  Positioner,
  Popup,
  Item,
  Separator,
  RadioGroup,
  RadioItem,
  GroupLabel,
  Group,
};
