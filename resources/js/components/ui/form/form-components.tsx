import { type ComponentProps, useCallback } from 'react';

import { useStore } from '@tanstack/react-form';

import { Button } from '@/components/ui/button';
import { useFormContext } from '@/components/ui/form/form-context';
import { cn } from '@/lib/cn';

interface SubmitButtonProps extends ComponentProps<typeof Button> {
  submittingText?: string;
}

export function SubmitButton({
  children,
  submittingText,
  className,
  disabled,
  ...props
}: SubmitButtonProps) {
  const form = useFormContext();

  const [isSubmitting, canSubmit] = useStore(form.store, (state) => [
    state.isSubmitting,
    state.canSubmit,
  ]);

  const handleClick = useCallback(() => {
    void form.handleSubmit();
  }, [form]);

  return (
    <Button
      {...props}
      type="button"
      className={className}
      disabled={!canSubmit || isSubmitting || disabled}
      onClick={handleClick}
    >
      {isSubmitting ? submittingText : children}
    </Button>
  );
}

export function FormRoot({ className, ...props }: Omit<ComponentProps<'form'>, 'onSubmit'>) {
  const form = useFormContext();

  const onSubmit = useCallback(
    (e: React.FormEvent<HTMLFormElement>) => {
      e.preventDefault();
      e.stopPropagation();
      void form.handleSubmit();
    },
    [form],
  );

  return (
    <form
      {...props}
      noValidate
      className={cn('flex w-full flex-col gap-4', className)}
      onSubmit={onSubmit}
    />
  );
}
