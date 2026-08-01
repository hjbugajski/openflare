import { type ComponentProps, useCallback, useId } from 'react';

import { useStore } from '@tanstack/react-form';

import { Button } from '@/components/ui/button';
import { useFormContext } from '@/components/ui/form/form-context';
import { cn } from '@/lib/cn';

const formIds = new WeakMap<object, string>();

/**
 * Ties a submit button to its `<form>` by id, so the two can live in separate
 * subtrees (the button usually sits in a card footer outside the form, where
 * context from `FormRoot` could never reach it). Keyed on the form instance
 * both components read from context: whichever renders first — always the
 * `<form>` in practice — donates its `useId` to the other.
 */
function useFormId(form: object): string {
  const ownId = useId();
  const sharedId = formIds.get(form);

  if (sharedId) {
    return sharedId;
  }

  formIds.set(form, ownId);

  return ownId;
}

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
  const formId = useFormId(form);

  const [isSubmitting, canSubmit] = useStore(form.store, (state) => [
    state.isSubmitting,
    state.canSubmit,
  ]);

  return (
    <Button
      {...props}
      type="submit"
      form={formId}
      className={className}
      disabled={!canSubmit || isSubmitting || disabled}
    >
      {isSubmitting ? submittingText : children}
    </Button>
  );
}

export function FormRoot({ className, ...props }: Omit<ComponentProps<'form'>, 'onSubmit'>) {
  const form = useFormContext();
  const formId = useFormId(form);

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
      id={formId}
      noValidate
      className={cn('flex w-full flex-col gap-4', className)}
      onSubmit={onSubmit}
    />
  );
}
