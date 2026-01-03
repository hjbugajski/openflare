import { Head } from '@inertiajs/react';
import { z } from 'zod';

import { Card } from '@/components/ui/card';
import { useInertiaAppForm } from '@/components/ui/form/use-inertia-app-form';
import { Heading } from '@/components/ui/heading';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/password/confirm';

const confirmPasswordSchema = z.object({
  password: z.string().min(1, 'password is required'),
});

export default function ConfirmPassword() {
  const { form, getServerError } = useInertiaAppForm({
    defaultValues: {
      password: '',
    },
    action: store().url,
    method: 'post',
    validators: {
      onSubmit: confirmPasswordSchema,
    },
  });

  return (
    <AuthLayout>
      <Head title="Confirm Password" />

      <Card.Root>
        <Card.Header>
          <Heading title="Confirm Password" />
          <p className="text-sm text-muted-foreground">please confirm your password to continue</p>
        </Card.Header>

        <Card.Content>
          <form.AppForm>
            <form.FormRoot>
              <form.AppField name="password">
                {(field) => (
                  <field.Field label="password" serverError={getServerError('password')}>
                    <field.TextInput autoFocus type="password" autoComplete="current-password" />
                  </field.Field>
                )}
              </form.AppField>

              <form.SubmitButton submittingText="confirming...">confirm</form.SubmitButton>
            </form.FormRoot>
          </form.AppForm>
        </Card.Content>
      </Card.Root>
    </AuthLayout>
  );
}
