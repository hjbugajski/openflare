import { Head } from '@inertiajs/react';

import { MonitorForm } from '@/components/monitors/monitor-form';
import { Heading } from '@/components/ui/heading';
import AppLayout from '@/layouts/app-layout';
import { show, update } from '@/routes/monitors';
import { type HttpMethod, type Monitor, type NotifierSummary } from '@/types';

interface Props {
  monitor: Monitor & { notifiers: NotifierSummary[] };
  notifiers: NotifierSummary[];
  intervals: number[];
  methods: HttpMethod[];
}

export default function MonitorsEdit({ monitor, notifiers, intervals, methods }: Props) {
  return (
    <AppLayout size="sm">
      <Head title={`edit ${monitor.name}`} />
      <Heading title="edit monitor" />
      <MonitorForm
        defaultValues={{
          name: monitor.name,
          url: monitor.url,
          method: monitor.method,
          interval: monitor.interval,
          timeout: monitor.timeout,
          expected_status_code: monitor.expected_status_code,
          is_active: monitor.is_active,
          notifiers: monitor.notifiers.map((n) => n.id),
        }}
        notifiers={notifiers}
        intervals={intervals}
        methods={methods}
        action={update(monitor.id).url}
        method="put"
        submitLabel="save"
        submittingLabel="saving..."
        cancelHref={show(monitor.id).url}
      />
    </AppLayout>
  );
}
