import type { ComponentProps } from 'react';

import { cn } from '@/lib/cn';

function Root({ className, ...props }: ComponentProps<'div'>) {
  return (
    <div
      className={cn(
        'relative flex w-full flex-col gap-4 border-t border-accent bg-background-secondary p-4 text-foreground',
        className,
      )}
      {...props}
    />
  );
}

function Header({ className, ...props }: ComponentProps<'div'>) {
  return <div className={cn('flex flex-col gap-1', className)} {...props} />;
}

function Description({ className, ...props }: ComponentProps<'p'>) {
  return <p className={cn('text-sm text-muted-foreground', className)} {...props} />;
}

function Content({ className, ...props }: ComponentProps<'div'>) {
  return <div className={className} {...props} />;
}

function Footer({ className, ...props }: ComponentProps<'div'>) {
  return (
    <div
      className={cn(
        'flex items-center border-t border-border pt-4 text-sm text-muted-foreground',
        className,
      )}
      {...props}
    />
  );
}

export const Card = {
  Root,
  Header,
  Description,
  Content,
  Footer,
};
