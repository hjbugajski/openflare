import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { useAppForm } from '@/components/ui/form/create-form';

interface FieldHarnessProps {
  description?: string;
  serverError?: string;
}

const validators = {
  onChange: ({ value }: { value: string }) => (value ? undefined : 'name is required'),
};

function FieldHarness({ description, serverError }: FieldHarnessProps) {
  const form = useAppForm({ defaultValues: { name: 'alerts' } });

  return (
    <form.AppForm>
      <form.FormRoot>
        <form.AppField name="name" validators={validators}>
          {(field) => (
            <field.Field label="name" description={description} serverError={serverError}>
              <field.TextInput />
            </field.Field>
          )}
        </form.AppField>
      </form.FormRoot>
    </form.AppForm>
  );
}

function nameInput(): HTMLInputElement {
  return screen.getByLabelText(/name/) as HTMLInputElement;
}

function describedElements(input: HTMLElement): HTMLElement[] {
  const describedBy = input.getAttribute('aria-describedby');

  if (!describedBy) {
    return [];
  }

  return describedBy.split(' ').map((id) => {
    const element = document.getElementById(id);

    if (!element) {
      throw new Error(`aria-describedby points at missing element #${id}`);
    }

    return element;
  });
}

function makeInvalid() {
  fireEvent.change(nameInput(), { target: { value: '' } });
  fireEvent.blur(nameInput());
}

describe('Field accessibility wiring', () => {
  it('describes the input with the validation error and marks it invalid', () => {
    render(<FieldHarness />);

    makeInvalid();

    const input = nameInput();

    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveClass('aria-invalid:border-danger');
    expect(describedElements(input).map((element) => element.textContent)).toEqual([
      expect.stringContaining('name is required'),
    ]);
  });

  // A server-rejected field is never "touched", so it has to be marked invalid
  // off the serverError prop or the danger border only ever shows for
  // client-side rules.
  it('describes the input with a server error and marks it invalid', () => {
    render(<FieldHarness serverError="that name is taken" />);

    const input = nameInput();

    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveClass('aria-invalid:border-danger');
    expect(describedElements(input).map((element) => element.textContent)).toEqual([
      expect.stringContaining('that name is taken'),
    ]);
  });

  it('describes the input with its description', () => {
    render(<FieldHarness description="shown in notifications" />);

    expect(describedElements(nameInput()).map((element) => element.textContent)).toEqual([
      expect.stringContaining('shown in notifications'),
    ]);
  });

  it('describes the input with both the description and the error', () => {
    render(<FieldHarness description="shown in notifications" />);

    makeInvalid();

    expect(describedElements(nameInput()).map((element) => element.textContent)).toEqual([
      expect.stringContaining('shown in notifications'),
      expect.stringContaining('name is required'),
    ]);
  });

  it('describes nothing when neither a description nor an error is rendered', () => {
    render(<FieldHarness />);

    expect(nameInput()).not.toHaveAttribute('aria-describedby');
    expect(nameInput()).not.toHaveAttribute('aria-invalid');
  });
});
