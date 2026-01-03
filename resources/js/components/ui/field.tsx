import { type ComponentProps, useId } from 'react';

import { Description, ErrorMessage, Label } from '@/components/ui/label';
import { cn } from '@/lib/cn';

interface FieldProps extends ComponentProps<'div'> {
  label: string;
  description?: string;
  error?: string;
  htmlFor?: string;
}

function Field({ className, children, label, description, error, htmlFor, ...props }: FieldProps) {
  const generatedId = useId();
  const fieldId = htmlFor ?? generatedId;

  return (
    <div {...props} className={cn('grid gap-2', className)}>
      <Label htmlFor={fieldId}>{label}</Label>
      {children}
      {description ? <Description>{description}</Description> : null}
      {error ? <ErrorMessage>{error}</ErrorMessage> : null}
    </div>
  );
}

export { Field };
