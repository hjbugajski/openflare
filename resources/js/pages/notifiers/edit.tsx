import { Head } from '@inertiajs/react';

import { NotifierForm } from '@/components/notifiers/notifier-form';
import { Heading } from '@/components/ui/heading';
import AppLayout from '@/layouts/app-layout';
import { index as notifiersIndex, update } from '@/routes/notifiers';
import { type MonitorSummary, type Notifier, type NotifierType } from '@/types';

interface Props {
  notifier: Notifier & { monitors: MonitorSummary[] };
  monitors: MonitorSummary[];
  types: NotifierType[];
}

export default function NotifiersEdit({ notifier, monitors, types }: Props) {
  return (
    <AppLayout size="sm">
      <Head title={`Edit ${notifier.name}`} />
      <Heading title="edit notifier" />
      <NotifierForm
        defaultValues={{
          name: notifier.name,
          type: notifier.type,
          config: {
            webhook_url: notifier.config.webhook_url || '',
            email: notifier.config.email || '',
          },
          is_active: notifier.is_active,
          is_default: notifier.is_default,
          apply_to_existing: notifier.apply_to_all,
          monitors: notifier.monitors.map((m) => m.id),
        }}
        monitors={monitors}
        types={types}
        action={update(notifier.id).url}
        method="put"
        submitLabel="save"
        submittingLabel="saving..."
        cancelHref={notifiersIndex().url}
        initialMonitorMode={notifier.apply_to_all ? 'all' : 'manual'}
      />
    </AppLayout>
  );
}
