import { mergeProps } from '@base-ui/react/merge-props';
import { useRender } from '@base-ui/react/use-render';
import { type VariantProps, cva } from 'class-variance-authority';

import { cn } from '@/lib/cn';

const buttonVariants = cva(
  [
    'inline-flex items-center justify-center',
    'border outline-none',
    'font-semibold whitespace-nowrap',
    'transition',
    'focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-background',
    'disabled:pointer-events-none disabled:opacity-40',
  ],
  {
    variants: {
      variant: {
        default: cn(
          'border-accent bg-accent text-accent-foreground',
          'hover:bg-orange-100 hover:text-accent',
          'dark:hover:bg-orange-950 dark:hover:text-accent',
          'focus-visible:ring-accent',
        ),
        secondary: cn(
          'border-muted-foreground bg-transparent text-foreground',
          'hover:bg-muted',
          'focus-visible:ring-foreground',
        ),
        destructive: cn(
          'border-danger bg-danger text-danger-foreground',
          'hover:bg-red-100 hover:text-danger',
          'dark:hover:bg-red-950 dark:hover:text-danger',
          'focus-visible:ring-danger',
        ),
        tertiary: cn(
          'border-transparent bg-transparent text-muted-foreground',
          'hover:bg-muted hover:text-foreground',
          'focus-visible:ring-muted-foreground',
        ),
      },
      size: {
        default: 'h-8 px-3 text-sm',
        sm: 'h-6 px-2 text-xs',
        icon: 'size-6',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
);

type ButtonProps = useRender.ComponentProps<'button'> & VariantProps<typeof buttonVariants>;

function Button({ render, className, variant, size, ...props }: ButtonProps) {
  const element = useRender({
    defaultTagName: 'button',
    render,
    props: mergeProps<'button'>(
      { className: cn(buttonVariants({ variant, size }), className) },
      props,
    ),
  });

  return element;
}

export { Button };
