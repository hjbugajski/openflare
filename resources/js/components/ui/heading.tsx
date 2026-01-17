import { type VariantProps, cva } from 'class-variance-authority';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/cn';

const headingVariants = cva('font-semibold uppercase', {
  variants: {
    level: {
      1: 'text-lg',
      2: 'text-base',
      3: 'text-sm',
      4: 'text-sm',
      5: 'text-xs',
      6: 'text-xs',
    },
  },
  defaultVariants: {
    level: 1,
  },
});

type HeadingLevel = 1 | 2 | 3 | 4 | 5 | 6;

type HeadingTag = 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

interface HeadingProps
  extends Omit<ComponentProps<'h1'>, 'children'>, VariantProps<typeof headingVariants> {
  title: string;
  description?: string;
  level?: HeadingLevel;
}

const headingTags: Record<HeadingLevel, HeadingTag> = {
  1: 'h1',
  2: 'h2',
  3: 'h3',
  4: 'h4',
  5: 'h5',
  6: 'h6',
};

function Heading({ title, description, level = 1, className, ...props }: HeadingProps) {
  const Tag = headingTags[level];
  const hashes = '#'.repeat(level);

  const heading = (
    <Tag className={cn(headingVariants({ level }), !description && className)} {...props}>
      <span aria-hidden className="mr-2 text-accent">
        {hashes}
      </span>
      {title}
    </Tag>
  );

  if (!description) {
    return heading;
  }

  return (
    <div className={cn('flex flex-col gap-1', className)}>
      {heading}
      <p className="text-sm text-muted-foreground">
        <span aria-hidden className="mr-1">
          {'//'}
        </span>
        {description}
      </p>
    </div>
  );
}

export { Heading };
