import { Head, Link } from '@inertiajs/react';
import { z } from 'zod';

import { Card } from '@/components/ui/card';
import { useInertiaAppForm } from '@/components/ui/form/use-inertia-app-form';
import { Heading } from '@/components/ui/heading';
import AuthLayout from '@/layouts/auth-layout';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const loginSchema = z.object({
  email: z.email('invalid email address'),
  password: z.string().min(1, 'password is required'),
  remember: z.boolean(),
});

interface Props {
  status?: string;
  canRegister?: boolean;
}

export default function Login({ status, canRegister }: Props) {
  const { form, getServerError } = useInertiaAppForm({
    defaultValues: {
      email: '',
      password: '',
      remember: false,
    },
    action: store().url,
    method: 'post',
    validators: {
      onSubmit: loginSchema,
    },
  });

  return (
    <AuthLayout>
      <Head title="Login" />

      <Card.Root>
        <Card.Header>
          <Heading title="Login" />
        </Card.Header>

        <Card.Content>
          {status ? (
            <div className="mb-4 text-xs text-success">
              <span aria-hidden className="mr-1 text-success">
                [OK]
              </span>
              {status}
            </div>
          ) : null}

          <form.AppForm>
            <form.FormRoot>
              <form.AppField name="email">
                {(field) => (
                  <field.Field label="email" serverError={getServerError('email')}>
                    <field.TextInput autoFocus type="email" autoComplete="email" />
                  </field.Field>
                )}
              </form.AppField>

              <form.AppField name="password">
                {(field) => (
                  <field.Field label="password" serverError={getServerError('password')}>
                    <field.TextInput type="password" autoComplete="current-password" />
                  </field.Field>
                )}
              </form.AppField>

              <form.AppField name="remember">
                {(field) => <field.CheckboxField label="remember session" />}
              </form.AppField>

              <form.SubmitButton submittingText="logging in...">log in</form.SubmitButton>
            </form.FormRoot>
          </form.AppForm>
        </Card.Content>

        <Card.Footer className="justify-between gap-2">
          <p>
            <Link
              href={request().url}
              className="text-sm text-muted-foreground transition hover:text-foreground"
            >
              forgot password
            </Link>
          </p>
          {canRegister ? (
            <p>
              <Link href={register().url} className="text-accent transition hover:text-foreground">
                register
              </Link>
            </p>
          ) : null}
        </Card.Footer>
      </Card.Root>
    </AuthLayout>
  );
}
