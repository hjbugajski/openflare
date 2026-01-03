import { Head } from '@inertiajs/react';

import { DeleteAccountSection } from '@/components/account/delete-account-section';
import { PasswordSection } from '@/components/account/password-section';
import { ProfileSection } from '@/components/account/profile-section';
import { TwoFactorSection } from '@/components/account/two-factor-section';
import { MonitorViewPreference } from '@/components/settings/monitor-view-preference';
import { Divider } from '@/components/ui/divider';
import { Heading } from '@/components/ui/heading';
import AppLayout from '@/layouts/app-layout';
import type { MonitorViewMode, User } from '@/types';

interface Props {
  auth: {
    user: User;
  };
  mustVerifyEmail: boolean;
  twoFactorEnabled: boolean;
}

export default function Settings({ auth, mustVerifyEmail, twoFactorEnabled }: Props) {
  const monitorsView: MonitorViewMode = auth.user.preferences?.monitors_view ?? 'cards';

  return (
    <AppLayout size="sm">
      <Head title="Settings" />

      <Heading title="Settings" description="configure your preferences" />
      <MonitorViewPreference value={monitorsView} />

      <Divider />

      <Heading title="Account" description="manage your account" />
      <ProfileSection user={auth.user} mustVerifyEmail={mustVerifyEmail} />
      <PasswordSection />
      <TwoFactorSection enabled={twoFactorEnabled} />
      <DeleteAccountSection />
    </AppLayout>
  );
}
