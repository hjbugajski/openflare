import { Head } from '@inertiajs/react';

import { NotifierForm } from '@/components/notifiers/notifier-form';
import { Heading } from '@/components/ui/heading';
import AppLayout from '@/layouts/app-layout';
import { index as notifiersIndex, store } from '@/routes/notifiers';
import { type MonitorSummary, type NotifierType } from '@/types';

interface Props {
  monitors: MonitorSummary[];
  types: NotifierType[];
}

export default function NotifiersCreate({ monitors, types }: Props) {
  return (
    <AppLayout size="sm">
      <Head title="Create Notifier" />
      <Heading title="create notifier" />
      <NotifierForm
        defaultValues={{
          name: '',
          type: 'discord',
          config: {
            webhook_url: '',
            email: '',
          },
          is_active: true,
          is_default: false,
          apply_to_existing: true,
          monitors: [],
          excluded_monitors: [],
        }}
        monitors={monitors}
        types={types}
        action={store().url}
        method="post"
        submitLabel="create notifier"
        submittingLabel="creating..."
        cancelHref={notifiersIndex().url}
        initialMonitorMode="all"
      />
    </AppLayout>
  );
}
