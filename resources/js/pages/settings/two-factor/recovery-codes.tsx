import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Heading } from '@/components/ui/heading';
import { toast } from '@/components/ui/toast';
import TwoFactorLayout from '@/layouts/two-factor-layout';
import { show } from '@/routes/settings';
import { regenerate } from '@/routes/settings/two-factor/recovery-codes';

interface Props {
  recoveryCodes: string[];
}

export default function TwoFactorRecoveryCodes({ recoveryCodes }: Props) {
  const [isRegenerating, setIsRegenerating] = useState(false);

  const handleRegenerate = () => {
    setIsRegenerating(true);
    router.post(regenerate().url, undefined, {
      onSuccess: () => {
        toast.success({ title: 'recovery codes regenerated' });
      },
      onFinish: () => setIsRegenerating(false),
    });
  };

  return (
    <TwoFactorLayout>
      <Head title="Recovery Codes" />
      <Card.Root>
        <Card.Header>
          <Heading level={2} title="Recovery Codes" />
        </Card.Header>
        <Card.Content className="space-y-4">
          <p>
            store these codes in a secure password manager. they can be used to recover access to
            your account if you lose your authenticator device. each code can only be used once.
          </p>

          <div className="flex flex-col gap-1 border border-border bg-background p-4 text-sm">
            {recoveryCodes.map((code, index) => (
              <div key={index}>{code}</div>
            ))}
          </div>

          <p>
            <span aria-hidden className="text-accent">
              [!]
            </span>{' '}
            make sure to save these codes before leaving this page.
          </p>
        </Card.Content>
        <Card.Footer className="justify-end gap-2">
          <Button variant="secondary" disabled={isRegenerating} onClick={handleRegenerate}>
            {isRegenerating ? 'regenerating...' : 'regenerate codes'}
          </Button>
          <Button render={<Link href={show().url} />}>done</Button>
        </Card.Footer>
      </Card.Root>
    </TwoFactorLayout>
  );
}
