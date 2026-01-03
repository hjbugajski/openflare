import type { PropsWithChildren } from 'react';

import { Heading } from '@/components/ui/heading';
import AppLayout from '@/layouts/app-layout';

export default function TwoFactorLayout({ children }: PropsWithChildren) {
  return (
    <AppLayout size="sm">
      <Heading title="Two-Factor Authentication" />
      {children}
    </AppLayout>
  );
}
