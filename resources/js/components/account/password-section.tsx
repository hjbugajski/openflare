import { z } from 'zod';

import { Card } from '@/components/ui/card';
import { useInertiaAppForm } from '@/components/ui/form/use-inertia-app-form';
import { Heading } from '@/components/ui/heading';
import { toast } from '@/components/ui/toast';
import { update } from '@/routes/settings/password';

const passwordSchema = z
  .object({
    current_password: z.string().min(1, 'current password is required'),
    password: z.string().min(8, 'password must be at least 8 characters'),
    password_confirmation: z.string().min(1, 'please confirm your password'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'passwords do not match',
    path: ['password_confirmation'],
  });

export function PasswordSection() {
  const { form, getServerError } = useInertiaAppForm({
    defaultValues: {
      current_password: '',
      password: '',
      password_confirmation: '',
    },
    action: update().url,
    method: 'put',
    validators: {
      onSubmit: passwordSchema,
    },
    onSuccess: () => {
      form.reset();
      toast.success({ title: 'password updated successfully' });
    },
  });

  return (
    <Card.Root>
      <Card.Header>
        <Heading level={2} title="Password" />
      </Card.Header>
      <Card.Content>
        <form.AppForm>
          <form.FormRoot>
            <form.AppField name="current_password">
              {(field) => (
                <field.Field
                  label="current password"
                  serverError={getServerError('current_password')}
                >
                  <field.TextInput type="password" autoComplete="current-password" />
                </field.Field>
              )}
            </form.AppField>

            <form.AppField name="password">
              {(field) => (
                <field.Field label="new password" serverError={getServerError('password')}>
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
          </form.FormRoot>
        </form.AppForm>
      </Card.Content>
      <Card.Footer className="justify-end">
        <form.AppForm>
          <form.SubmitButton submittingText="updating...">update password</form.SubmitButton>
        </form.AppForm>
      </Card.Footer>
    </Card.Root>
  );
}
