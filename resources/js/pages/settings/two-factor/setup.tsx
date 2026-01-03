import { Head, Link } from '@inertiajs/react';
// eslint-disable-next-line import/no-named-as-default
import DOMPurify from 'dompurify';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Heading } from '@/components/ui/heading';
import TwoFactorLayout from '@/layouts/two-factor-layout';
import { confirm } from '@/routes/settings/two-factor';

interface Props {
  qrCodeSvg: string;
  secretKey: string;
  setupKey: string;
}

export default function TwoFactorSetup({ qrCodeSvg, secretKey }: Props) {
  return (
    <TwoFactorLayout>
      <Head title="Set Up 2FA" />
      <Card.Root>
        <Card.Header>
          <Heading level={2} title="Set Up 2FA" />
        </Card.Header>
        <Card.Content className="flex flex-col gap-4">
          <div className="space-y-2">
            <p>scan this QR code with your authenticator app:</p>
            <div
              className="mx-auto w-fit bg-paper p-2"
              dangerouslySetInnerHTML={{
                __html: DOMPurify.sanitize(qrCodeSvg, {
                  USE_PROFILES: { svg: true, svgFilters: true },
                }),
              }}
            />
          </div>
          <div className="space-y-2">
            <p>or enter this code manually:</p>
            <code className="block border border-border bg-background px-3 py-2 text-center select-all">
              {secretKey}
            </code>
          </div>
        </Card.Content>
        <Card.Footer className="justify-end">
          <Button render={<Link href={confirm().url} />}>next</Button>
        </Card.Footer>
      </Card.Root>
    </TwoFactorLayout>
  );
}
