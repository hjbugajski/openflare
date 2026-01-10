import type { ComponentProps } from 'react';

import { cn } from '@/lib/cn';

function Root({ className, ...props }: ComponentProps<'dl'>) {
  return <dl className={cn('grid grid-cols-2 gap-4 sm:grid-cols-4', className)} {...props} />;
}

function Card({ className, ...props }: ComponentProps<'div'>) {
  return (
    <div
      className={cn(
        'flex w-full flex-col gap-4 border-t border-accent bg-background-secondary p-4 text-foreground',
        className,
      )}
      {...props}
    />
  );
}

function Term({ className, ...props }: ComponentProps<'dt'>) {
  return (
    <dt className={cn('text-sm font-medium text-muted-foreground uppercase', className)} {...props}>
      <span aria-hidden className="text-accent">
        &gt;
      </span>{' '}
      {props.children}
    </dt>
  );
}

function Value({ className, ...props }: ComponentProps<'dd'>) {
  return <dd className={cn('text-xl', className)} {...props} />;
}

export const Stats = {
  Root,
  Card,
  Term,
  Value,
};
