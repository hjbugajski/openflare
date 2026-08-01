import { useCallback, useState } from 'react';

import { router } from '@inertiajs/react';

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
  const [isToggling, setIsToggling] = useState(false);

  const handleEnable = useCallback(() => {
    setIsToggling(true);
    router.post(enable().url, undefined, { onFinish: () => setIsToggling(false) });
  }, []);

  const handleDisable = useCallback(() => {
    setIsToggling(true);
    router.delete(disable().url, {
      onSuccess: () => {
        toast.success({ title: '2FA disabled' });
      },
      onFinish: () => setIsToggling(false),
    });
  }, []);

  const handleRegenerate = useCallback(() => {
    setIsRegenerating(true);
    router.post(regenerate().url, undefined, {
      onSuccess: () => {
        toast.success({ title: 'recovery codes regenerated' });
      },
      onFinish: () => setIsRegenerating(false),
    });
  }, []);

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
            <Button variant="destructive" disabled={isToggling} onClick={handleDisable}>
              {isToggling ? 'disabling...' : 'disable 2FA'}
            </Button>
          </>
        ) : (
          <Button disabled={isToggling} onClick={handleEnable}>
            {isToggling ? 'enabling...' : 'enable 2FA'}
          </Button>
        )}
      </Card.Footer>
    </Card.Root>
  );
}
