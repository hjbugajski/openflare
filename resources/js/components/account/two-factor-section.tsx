import { useState } from 'react';

import { Link, router } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Heading } from '@/components/ui/heading';
import { toast } from '@/components/ui/toast';
import { disable, enable } from '@/routes/settings/two-factor';
import { regenerate } from '@/routes/settings/two-factor/recovery-codes';

interface TwoFactorSectionProps {
  enabled: boolean;
}

export function TwoFactorSection({ enabled }: TwoFactorSectionProps) {
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
    <Card.Root>
      <Card.Header>
        <Heading level={2} title="Two-Factor Authentication" />
      </Card.Header>
      <Card.Content className="space-y-4">
        <p>{enabled ? '2FA is enabled.' : '2FA is disabled.'}</p>
      </Card.Content>
      <Card.Footer className="justify-end gap-2">
        {enabled ? (
          <>
            <Button variant="secondary" disabled={isRegenerating} onClick={handleRegenerate}>
              {isRegenerating ? 'regenerating...' : 'regenerate recovery codes'}
            </Button>
            <Button variant="destructive" render={<Link href={disable().url} />}>
              disable 2FA
            </Button>
          </>
        ) : (
          <Button render={<Link href={enable().url} />}>enable 2FA</Button>
        )}
      </Card.Footer>
    </Card.Root>
  );
}
