import type { ComponentProps } from 'react';

import { cn } from '@/lib/cn';

type InputSize = 'default' | 'sm';

type InputProps = ComponentProps<'input'> & {
  inputSize?: InputSize;
};

const inputSizeClasses: Record<InputSize, string> = {
  default: 'h-9 px-3 py-2 text-sm',
  sm: 'h-6 px-2 py-1 text-xs',
};

function Input({ className, type, inputSize = 'default', ...props }: InputProps) {
  return (
    <input
      type={type}
      className={cn(
        'flex w-full border border-border bg-background text-foreground transition outline-none',
        inputSizeClasses[inputSize],
        'placeholder:text-muted-foreground',
        'focus-visible:border-muted-foreground focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-1 focus-visible:ring-offset-background',
        'disabled:cursor-not-allowed disabled:opacity-50',
        className,
      )}
      {...props}
    />
  );
}

export { Input };
