import { Head } from '@inertiajs/react';
import { z } from 'zod';

import { Card } from '@/components/ui/card';
import { useInertiaAppForm } from '@/components/ui/form/use-inertia-app-form';
import { Heading } from '@/components/ui/heading';
import AuthLayout from '@/layouts/auth-layout';
import { passwordSchema } from '@/lib/schemas/password';
import { update } from '@/routes/password';

const resetPasswordSchema = z
  .object({
    token: z.string(),
    email: z.email('invalid email address'),
    password: passwordSchema,
    password_confirmation: z.string().min(1, 'please confirm your password'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'passwords do not match',
    path: ['password_confirmation'],
  });

interface Props {
  email: string;
  token: string;
}

export default function ResetPassword({ email, token }: Props) {
  const { form, getServerError } = useInertiaAppForm({
    defaultValues: {
      token,
      email,
      password: '',
      password_confirmation: '',
    },
    action: update().url,
    method: 'post',
    validators: {
      onSubmit: resetPasswordSchema,
    },
  });

  return (
    <AuthLayout>
      <Head title="Reset Password" />

      <Card.Root>
        <Card.Header>
          <Heading title="Reset password" />
        </Card.Header>

        <Card.Content>
          <form.AppForm>
            <form.FormRoot>
              <form.AppField name="email">
                {(field) => (
                  <field.Field label="email" serverError={getServerError('email')}>
                    <field.TextInput readOnly type="email" autoComplete="username" />
                  </field.Field>
                )}
              </form.AppField>

              <form.AppField name="password">
                {(field) => (
                  <field.Field label="new password" serverError={getServerError('password')}>
                    <field.TextInput autoFocus type="password" autoComplete="new-password" />
                  </field.Field>
                )}
              </form.AppField>

              <form.AppField name="password_confirmation">
                {(field) => (
                  <field.Field
                    label="confirm password"
                    serverError={getServerError('password_confirmation')}
                  >
                    <field.TextInput type="password" autoComplete="new-password" />
                  </field.Field>
                )}
              </form.AppField>

              <form.SubmitButton submittingText="resetting...">reset password</form.SubmitButton>
            </form.FormRoot>
          </form.AppForm>
        </Card.Content>
      </Card.Root>
    </AuthLayout>
  );
}
