import { useState } from 'react';

import { Head } from '@inertiajs/react';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useInertiaAppForm } from '@/components/ui/form/use-inertia-app-form';
import { Heading } from '@/components/ui/heading';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/two-factor/login';

const twoFactorChallengeSchema = z
  .object({
    code: z.string(),
    recovery_code: z.string(),
  })
  .refine((data) => data.code.length > 0 || data.recovery_code.length > 0, {
    message: 'code or recovery code is required',
    path: ['code'],
  });

export default function TwoFactorChallenge() {
  const [recovery, setRecovery] = useState(false);
  const { form, getServerError } = useInertiaAppForm({
    defaultValues: {
      code: '',
      recovery_code: '',
    },
    action: store().url,
    method: 'post',
    validators: {
      onSubmit: twoFactorChallengeSchema,
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
                <Button variant="tertiary" onClick={() => setRecovery(!recovery)}>
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
