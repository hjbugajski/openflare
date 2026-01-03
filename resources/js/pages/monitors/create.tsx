import { Head } from '@inertiajs/react';

import { MonitorForm } from '@/components/monitors/monitor-form';
import { Heading } from '@/components/ui/heading';
import AppLayout from '@/layouts/app-layout';
import { index as monitorsIndex, store } from '@/routes/monitors';
import { type HttpMethod, type NotifierSummary } from '@/types';

interface Props {
  notifiers: NotifierSummary[];
  intervals: number[];
  methods: HttpMethod[];
}

export default function MonitorsCreate({ notifiers, intervals, methods }: Props) {
  return (
    <AppLayout size="sm">
      <Head title="Create Monitor" />
      <Heading title="create monitor" />
      <MonitorForm
        defaultValues={{
          name: '',
          url: '',
          method: 'GET',
          interval: 300,
          timeout: 30,
          expected_status_code: 200,
          is_active: true,
          notifiers: [],
        }}
        notifiers={notifiers}
        intervals={intervals}
        methods={methods}
        action={store().url}
        method="post"
        submitLabel="create"
        submittingLabel="creating..."
        cancelHref={monitorsIndex().url}
      />
    </AppLayout>
  );
}
