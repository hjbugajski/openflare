import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Heading } from '@/components/ui/heading';
import AuthLayout from '@/layouts/auth-layout';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

interface Props {
  status?: string;
}

export default function VerifyEmail({ status }: Props) {
  const [processing, setProcessing] = useState(false);

  const resendVerification = () => {
    router.post(
      send().url,
      {},
      { onStart: () => setProcessing(true), onFinish: () => setProcessing(false) },
    );
  };

  return (
    <AuthLayout>
      <Head title="Verify Email" />

      <Card.Root>
        <Card.Header>
          <Heading title="Verify email" />
        </Card.Header>

        <Card.Content className="flex flex-col gap-4">
          {status === 'verification-link-sent' ? (
            <p className="text-sm text-success">
              <span aria-hidden className="mr-1">
                [OK]
              </span>
              a new verification link has been sent.
            </p>
          ) : null}

          <p>Check your inbox for a verification link.</p>

          <Button disabled={processing} onClick={resendVerification}>
            {processing ? 'sending...' : 'resend verification email'}
          </Button>
        </Card.Content>

        <Card.Footer>
          <Link
            href={logout().url}
            method="post"
            as="button"
            className="text-sm text-muted-foreground transition hover:text-foreground"
          >
            logout
          </Link>
        </Card.Footer>
      </Card.Root>
    </AuthLayout>
  );
}
