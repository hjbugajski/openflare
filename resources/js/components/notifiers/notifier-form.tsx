import { useCallback, useState } from 'react';

import { Link } from '@inertiajs/react';
import { useStore } from '@tanstack/react-form';

import { type MonitorMode, MonitorModeRadio } from '@/components/notifiers/monitor-mode-radio';
import { useNotifierTest } from '@/components/notifiers/use-notifier-test';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useInertiaAppForm } from '@/components/ui/form/use-inertia-app-form';
import { Label } from '@/components/ui/label';
import {
  NOTIFIER_TYPE_DESCRIPTIONS,
  NOTIFIER_TYPE_LABELS,
  type NotifierFormValues,
  notifierSchema,
} from '@/lib/schemas/notifier';
import { type MonitorSummary, type NotifierType } from '@/types';

export type { NotifierFormValues };

export interface NotifierFormProps {
  defaultValues: NotifierFormValues;
  monitors: MonitorSummary[];
  types: NotifierType[];
  action: string;
  method: 'post' | 'put';
  submitLabel: string;
  submittingLabel: string;
  cancelHref: string;
  initialMonitorMode: MonitorMode;
}

export function NotifierForm({
  defaultValues,
  monitors,
  types,
  action,
  method,
  submitLabel,
  submittingLabel,
  cancelHref,
  initialMonitorMode,
}: NotifierFormProps) {
  const [monitorMode, setMonitorMode] = useState<MonitorMode>(initialMonitorMode);

  const { form, getServerError } = useInertiaAppForm({
    defaultValues,
    action,
    method,
    validators: {
      onSubmit: notifierSchema,
    },
  });

  const currentType = useStore(form.store, (state) => state.values.type);

  const { isTesting, handleTest } = useNotifierTest({
    getType: useCallback(() => form.getFieldValue('type'), [form]),
    getConfig: useCallback(() => form.getFieldValue('config'), [form]),
  });

  const handleModeChange = (value: MonitorMode) => {
    setMonitorMode(value);
    form.setFieldValue('apply_to_existing', value === 'all');
    if (value === 'all') {
      form.setFieldValue('monitors', []);
    }
  };

  return (
    <Card.Root>
      <form.AppForm>
        <form.FormRoot>
          <form.AppField name="name">
            {(field) => (
              <field.Field label="name" serverError={getServerError('name')}>
                <field.TextInput placeholder="My Discord Server" />
              </field.Field>
            )}
          </form.AppField>

          <form.AppField name="type">
            {(field) => (
              <field.Field
                label="type"
                description={NOTIFIER_TYPE_DESCRIPTIONS[field.state.value as NotifierType]}
                serverError={getServerError('type')}
              >
                <field.SelectField
                  items={types.map((type) => ({
                    value: type,
                    label: NOTIFIER_TYPE_LABELS[type as NotifierType] || type,
                  }))}
                />
              </field.Field>
            )}
          </form.AppField>

          {currentType === 'discord' ? (
            <form.AppField name="config.webhook_url">
              {(field) => (
                <field.Field
                  label="webhook URL"
                  description="create a webhook in your Discord server settings under Integrations"
                  serverError={getServerError('config.webhook_url')}
                >
                  <div className="flex items-center gap-2">
                    <field.TextInput
                      required
                      type="url"
                      placeholder="https://discord.com/api/webhooks/..."
                    />
                    <Button
                      type="button"
                      variant="secondary"
                      disabled={isTesting}
                      onClick={handleTest}
                    >
                      {isTesting ? 'testing...' : 'test'}
                    </Button>
                  </div>
                </field.Field>
              )}
            </form.AppField>
          ) : null}

          {currentType === 'email' ? (
            <form.AppField name="config.email">
              {(field) => (
                <field.Field label="email address" serverError={getServerError('config.email')}>
                  <div className="flex items-center gap-2">
                    <field.TextInput type="email" placeholder="alerts@example.com" />
                    <Button
                      type="button"
                      variant="secondary"
                      disabled={isTesting}
                      onClick={handleTest}
                    >
                      {isTesting ? 'testing...' : 'test'}
                    </Button>
                  </div>
                </field.Field>
              )}
            </form.AppField>
          ) : null}

          {monitors.length > 0 ? (
            <div className="grid gap-2">
              <Label>monitors</Label>
              <MonitorModeRadio value={monitorMode} onValueChange={handleModeChange} />
            </div>
          ) : null}

          {monitorMode === 'manual' ? (
            <form.AppField name="monitors">
              {(field) => (
                <field.Field label="select monitors" serverError={getServerError('monitors')}>
                  <field.ComboboxField
                    items={monitors.map((m) => ({
                      id: m.id,
                      label: m.name,
                    }))}
                    emptyMessage="no monitors found."
                    placeholder="select monitors..."
                  />
                </field.Field>
              )}
            </form.AppField>
          ) : null}

          <form.AppField name="is_active">
            {(field) => <field.CheckboxField label="active" />}
          </form.AppField>

          <form.AppField name="is_default">
            {(field) => (
              <field.CheckboxField
                label="enable by default for new monitors"
                description="automatically attach this notifier to newly created monitors"
              />
            )}
          </form.AppField>
        </form.FormRoot>
        <Card.Footer className="justify-end gap-2">
          <Button variant="secondary" render={<Link href={cancelHref} />}>
            cancel
          </Button>
          <form.SubmitButton submittingText={submittingLabel}>{submitLabel}</form.SubmitButton>
        </Card.Footer>
      </form.AppForm>
    </Card.Root>
  );
}
