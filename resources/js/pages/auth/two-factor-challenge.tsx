import { useCallback, useState } from 'react';

import { Head } from '@inertiajs/react';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useInertiaAppForm } from '@/components/ui/form/use-inertia-app-form';
import { Heading } from '@/components/ui/heading';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/two-factor/login';

/*
 * Only one of the two fields is mounted at a time, so each mode validates its
 * own field and binds the error to that field's path — an error on the hidden
 * field would never reach the screen.
 */
const codeSchema = z.object({
  code: z.string().min(1, 'authentication code is required'),
  recovery_code: z.string(),
});

const recoveryCodeSchema = z.object({
  code: z.string(),
  recovery_code: z.string().min(1, 'recovery code is required'),
});

export default function TwoFactorChallenge() {
  const [recovery, setRecovery] = useState(false);
  const toggleRecovery = useCallback(() => setRecovery((prev) => !prev), []);
  const { form, getServerError } = useInertiaAppForm({
    defaultValues: {
      code: '',
      recovery_code: '',
    },
    action: store().url,
    method: 'post',
    validators: {
      onSubmit: recovery ? recoveryCodeSchema : codeSchema,
    },
  });

  return (
    <AuthLayout>
      <Head title="Two-Factor Authentication" />
      <Card.Root>
        <Card.Header>
          <Heading title="Two-Factor Authentication" />
        </Card.Header>
        <Card.Content className="flex flex-col gap-4">
          <p>
            {recovery
              ? 'enter one of your emergency recovery codes.'
              : 'enter the authentication code provided by your authenticator application.'}
          </p>
          <form.AppForm>
            <form.FormRoot>
              {recovery ? (
                <form.AppField name="recovery_code">
                  {(field) => (
                    <field.Field
                      label="recovery code"
                      serverError={getServerError('recovery_code')}
                    >
                      <field.TextInput autoFocus type="text" autoComplete="one-time-code" />
                    </field.Field>
                  )}
                </form.AppField>
              ) : (
                <form.AppField name="code">
                  {(field) => (
                    <field.Field label="authentication code" serverError={getServerError('code')}>
                      <field.TextInput autoFocus type="text" autoComplete="one-time-code" />
                    </field.Field>
                  )}
                </form.AppField>
              )}

              <div className="flex items-center justify-end gap-4">
                <Button variant="tertiary" onClick={toggleRecovery}>
                  {recovery ? 'use an authentication code' : 'use a recovery code'}
                </Button>
                <form.SubmitButton submittingText="verifying...">verify</form.SubmitButton>
              </div>
            </form.FormRoot>
          </form.AppForm>
        </Card.Content>
      </Card.Root>
    </AuthLayout>
  );
}
