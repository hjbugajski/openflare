import { Tabs as BaseTabs } from '@base-ui/react/tabs';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/cn';

function Root({ className, ...props }: ComponentProps<typeof BaseTabs.Root>) {
  return <BaseTabs.Root className={cn('flex w-full flex-col gap-4', className)} {...props} />;
}

function List({ className, ...props }: ComponentProps<typeof BaseTabs.List>) {
  return (
    <BaseTabs.List
      className={cn(
        'relative flex w-full items-center gap-1 overflow-x-auto border-b border-border',
        className,
      )}
      {...props}
    />
  );
}

function Tab({ className, ...props }: ComponentProps<typeof BaseTabs.Tab>) {
  return (
    <BaseTabs.Tab
      className={cn(
        'relative z-20 inline-flex h-8 w-fit items-center px-3',
        'text-sm font-medium whitespace-nowrap',
        'hover:not-data-disabled:text-accent',
        'focus-visible:bg-muted focus-visible:ring focus-visible:ring-accent focus-visible:outline-none',
        'data-selected:text-foreground',
        'data-disabled:cursor-not-allowed data-disabled:opacity-50',
        'transition',
        className,
      )}
      {...props}
    />
  );
}

function Indicator({ className, ...props }: ComponentProps<typeof BaseTabs.Indicator>) {
  return (
    <BaseTabs.Indicator
      className={cn(
        'absolute bottom-0 left-0 z-10 h-full w-(--active-tab-width) translate-x-(--active-tab-left) border-b border-accent bg-muted transition-all duration-200 ease-in-out',
        className,
      )}
      {...props}
    />
  );
}

function Panel({ className, ...props }: ComponentProps<typeof BaseTabs.Panel>) {
  return <BaseTabs.Panel className={cn('flex-1 outline-none', className)} {...props} />;
}

export const Tabs = {
  Root,
  List,
  Tab,
  Indicator,
  Panel,
};
