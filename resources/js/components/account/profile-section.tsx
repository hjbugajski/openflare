import { router } from '@inertiajs/react';
import { z } from 'zod';

import { Card } from '@/components/ui/card';
import { useInertiaAppForm } from '@/components/ui/form/use-inertia-app-form';
import { Heading } from '@/components/ui/heading';
import { toast } from '@/components/ui/toast';
import { update } from '@/routes/settings/profile';
import { send } from '@/routes/verification';
import type { User } from '@/types';

const profileSchema = z.object({
  name: z.string().min(1, 'name is required'),
  email: z.email('invalid email address'),
});

interface ProfileSectionProps {
  user: User;
  mustVerifyEmail: boolean;
}

export function ProfileSection({ user, mustVerifyEmail }: ProfileSectionProps) {
  const { form, getServerError } = useInertiaAppForm({
    defaultValues: {
      name: user.name,
      email: user.email,
    },
    action: update().url,
    method: 'patch',
    validators: {
      onSubmit: profileSchema,
    },
    onSuccess: () => {
      toast.success({ title: 'profile updated successfully' });
    },
  });

  return (
    <Card.Root>
      <Card.Header>
        <Heading level={2} title="Profile" />
      </Card.Header>
      <Card.Content className="flex flex-col gap-4">
        <form.AppForm>
          <form.FormRoot>
            <form.AppField name="name">
              {(field) => (
                <field.Field label="name" serverError={getServerError('name')}>
                  <field.TextInput type="text" autoComplete="name" />
                </field.Field>
              )}
            </form.AppField>

            <form.AppField name="email">
              {(field) => (
                <field.Field label="email" serverError={getServerError('email')}>
                  <field.TextInput type="email" autoComplete="username" />
                  {mustVerifyEmail && user.email_verified_at === null ? (
                    <p className="mt-2 text-sm text-muted-foreground">
                      your email address is unverified.
                      <button
                        type="button"
                        className="ml-1 text-accent hover:underline"
                        onClick={() => router.post(send().url)}
                      >
                        click here to re-send the verification email.
                      </button>
                    </p>
                  ) : null}
                </field.Field>
              )}
            </form.AppField>
          </form.FormRoot>
        </form.AppForm>
      </Card.Content>
      <Card.Footer className="justify-end">
        <form.AppForm>
          <form.SubmitButton submittingText="updating...">update profile</form.SubmitButton>
        </form.AppForm>
      </Card.Footer>
    </Card.Root>
  );
}
