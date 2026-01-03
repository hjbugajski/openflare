import { Head, Link } from '@inertiajs/react';
import { z } from 'zod';

import { Card } from '@/components/ui/card';
import { useInertiaAppForm } from '@/components/ui/form/use-inertia-app-form';
import { Heading } from '@/components/ui/heading';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { store } from '@/routes/register';

const registerSchema = z
  .object({
    name: z.string().min(1, 'name is required'),
    email: z.email('invalid email address'),
    password: z.string().min(8, 'password must be at least 8 characters'),
    password_confirmation: z.string().min(1, 'please confirm your password'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'passwords do not match',
    path: ['password_confirmation'],
  });

export default function Register() {
  const { form, getServerError } = useInertiaAppForm({
    defaultValues: {
      name: '',
      email: '',
      password: '',
      password_confirmation: '',
    },
    action: store().url,
    method: 'post',
    validators: {
      onSubmit: registerSchema,
    },
  });

  return (
    <AuthLayout>
      <Head title="Register" />

      <Card.Root>
        <Card.Header>
          <Heading title="Register" />
        </Card.Header>

        <Card.Content>
          <form.AppForm>
            <form.FormRoot>
              <form.AppField name="name">
                {(field) => (
                  <field.Field label="name" serverError={getServerError('name')}>
                    <field.TextInput autoFocus type="text" autoComplete="name" />
                  </field.Field>
                )}
              </form.AppField>

              <form.AppField name="email">
                {(field) => (
                  <field.Field label="email" serverError={getServerError('email')}>
                    <field.TextInput type="email" autoComplete="username" />
                  </field.Field>
                )}
              </form.AppField>

              <form.AppField name="password">
                {(field) => (
                  <field.Field label="password" serverError={getServerError('password')}>
                    <field.TextInput type="password" autoComplete="new-password" />
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

              <form.SubmitButton submittingText="creating...">create account</form.SubmitButton>
            </form.FormRoot>
          </form.AppForm>
        </Card.Content>

        <Card.Footer>
          <p>
            already have an account?{' '}
            <Link href={login().url} className="text-accent transition hover:text-foreground">
              sign in
            </Link>
          </p>
        </Card.Footer>
      </Card.Root>
    </AuthLayout>
  );
}
