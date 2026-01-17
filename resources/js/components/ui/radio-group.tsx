import { Radio } from '@base-ui/react/radio';
import { RadioGroup as BaseRadioGroup } from '@base-ui/react/radio-group';
import type { ComponentProps, ReactNode } from 'react';

import { cn } from '@/lib/cn';

type RootProps = Omit<ComponentProps<typeof BaseRadioGroup>, 'onValueChange'> & {
  onValueChange?: (value: string) => void;
};

function Root({ className, onValueChange, ...props }: RootProps) {
  return (
    <BaseRadioGroup
      className={cn('grid gap-2', className)}
      onValueChange={onValueChange ? (value) => onValueChange(value as string) : undefined}
      {...props}
    />
  );
}

interface ItemProps {
  value: string;
  children: ReactNode;
  checked?: boolean;
  disabled?: boolean;
  className?: string;
}

function Item({ value, children, checked, disabled, className }: ItemProps) {
  return (
    <label
      data-checked={checked}
      data-disabled={disabled || undefined}
      className={cn(
        'group flex cursor-pointer items-center gap-2 border border-border bg-background px-3 py-2 transition',
        'data-[checked="false"]:hover:border-border-hover data-[checked="true"]:border-accent data-[checked="true"]:text-accent',
        'data-disabled:pointer-events-none data-disabled:opacity-50',
        className,
      )}
    >
      <Radio.Root
        value={value}
        disabled={disabled}
        className="flex size-4 shrink-0 items-center justify-center rounded-full border border-border bg-background transition outline-none group-hover:border-border-hover focus-visible:ring-2 focus-visible:ring-accent data-checked:bg-accent"
      />
      <span className="text-sm">{children}</span>
    </label>
  );
}

export const RadioGroup = { Root, Item };
