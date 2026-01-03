import { Head, Link } from '@inertiajs/react';
import { z } from 'zod';

import { Card } from '@/components/ui/card';
import { useInertiaAppForm } from '@/components/ui/form/use-inertia-app-form';
import { Heading } from '@/components/ui/heading';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { email } from '@/routes/password';

const forgotPasswordSchema = z.object({
  email: z.email('invalid email address'),
});

interface Props {
  status?: string;
}

export default function ForgotPassword({ status }: Props) {
  const { form, getServerError } = useInertiaAppForm({
    defaultValues: {
      email: '',
    },
    action: email().url,
    method: 'post',
    validators: {
      onSubmit: forgotPasswordSchema,
    },
  });

  return (
    <AuthLayout>
      <Head title="Forgot Password" />

      <Card.Root>
        <Card.Header>
          <Heading title="Forgot password" description="enter your email to receive a reset link" />
        </Card.Header>
        <Card.Content>
          {status ? (
            <div className="mb-4 text-sm text-success">
              <span aria-hidden className="mr-1">
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
                    <field.TextInput autoFocus type="email" autoComplete="username" />
                  </field.Field>
                )}
              </form.AppField>

              <form.SubmitButton submittingText="sending...">send</form.SubmitButton>
            </form.FormRoot>
          </form.AppForm>
        </Card.Content>
        <Card.Footer>
          <p>
            <Link
              href={login().url}
              className="text-sm text-muted-foreground transition hover:text-foreground"
            >
              back to login
            </Link>
          </p>
        </Card.Footer>
      </Card.Root>
    </AuthLayout>
  );
}
