import { renderToString } from 'react-dom/server';

import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { Button } from '@/components/ui/button';
import { useAppForm } from '@/components/ui/form/create-form';

const onSubmit = vi.fn();
const onDecoyClick = vi.fn();

/*
 * Mirrors the account sections: the submit button lives in a card footer, in a
 * second <form.AppForm> that is not a descendant of the <form> element.
 */
function DetachedSubmitForm() {
  const form = useAppForm({
    defaultValues: { name: '', url: '' },
    onSubmit,
  });

  return (
    <div>
      <form.AppForm>
        <form.FormRoot>
          <form.AppField name="name">
            {(field) => (
              <field.Field label="name">
                <field.TextInput />
              </field.Field>
            )}
          </form.AppField>
          <form.AppField name="url">
            {(field) => (
              <field.Field label="url">
                <field.TextInput />
              </field.Field>
            )}
          </form.AppField>
          <Button onClick={onDecoyClick}>decoy</Button>
        </form.FormRoot>
      </form.AppForm>
      <div>
        <form.AppForm>
          <form.SubmitButton submittingText="saving...">save</form.SubmitButton>
        </form.AppForm>
      </div>
    </div>
  );
}

function typeName(value: string) {
  const input = screen.getByLabelText(/name/);

  fireEvent.change(input, { target: { value } });

  return input;
}

describe('form submission', () => {
  beforeEach(() => {
    onSubmit.mockClear();
    onDecoyClick.mockClear();
  });

  it('submits once when Enter is pressed in a text field', async () => {
    render(<DetachedSubmitForm />);

    fireEvent.keyDown(typeName('alerts'), { key: 'Enter' });

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1));

    expect(onSubmit.mock.calls[0]?.[0]).toMatchObject({ value: { name: 'alerts' } });
  });

  it('submits once when the detached submit button is clicked', async () => {
    render(<DetachedSubmitForm />);

    typeName('alerts');

    fireEvent.click(screen.getByRole('button', { name: 'save' }));

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1));
  });

  it('does not submit when a plain button inside the form is clicked', async () => {
    render(<DetachedSubmitForm />);

    typeName('alerts');

    fireEvent.click(screen.getByRole('button', { name: 'decoy' }));

    await waitFor(() => expect(onDecoyClick).toHaveBeenCalledTimes(1));

    expect(onSubmit).not.toHaveBeenCalled();
  });

  // The SSR process is long-lived, so an id counter would drift out of step
  // with the freshly loaded client and break hydration.
  it('renders the same form id on every server render', () => {
    expect(renderToString(<DetachedSubmitForm />)).toEqual(renderToString(<DetachedSubmitForm />));
  });
});
