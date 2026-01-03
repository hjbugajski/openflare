import { Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useInertiaAppForm } from '@/components/ui/form/use-inertia-app-form';
import { INTERVAL_LABELS, type MonitorFormValues, monitorSchema } from '@/lib/schemas/monitor';
import { type HttpMethod, type NotifierSummary } from '@/types';

export type { MonitorFormValues };

export interface MonitorFormProps {
  defaultValues: MonitorFormValues;
  notifiers: NotifierSummary[];
  intervals: number[];
  methods: HttpMethod[];
  action: string;
  method: 'post' | 'put';
  submitLabel: string;
  submittingLabel: string;
  cancelHref: string;
}

export function MonitorForm({
  defaultValues,
  notifiers,
  intervals,
  methods,
  action,
  method,
  submitLabel,
  submittingLabel,
  cancelHref,
}: MonitorFormProps) {
  const { form, getServerError } = useInertiaAppForm({
    defaultValues,
    action,
    method,
    validators: {
      onSubmit: monitorSchema,
    },
  });

  return (
    <Card.Root>
      <form.AppForm>
        <form.FormRoot>
          <form.AppField name="name">
            {(field) => (
              <field.Field label="name" serverError={getServerError('name')}>
                <field.TextInput />
              </field.Field>
            )}
          </form.AppField>

          <form.AppField name="url">
            {(field) => (
              <field.Field label="url" serverError={getServerError('url')}>
                <field.TextInput type="url" />
              </field.Field>
            )}
          </form.AppField>

          <div className="grid grid-cols-2 gap-4">
            <form.AppField name="method">
              {(field) => (
                <field.Field label="method" serverError={getServerError('method')}>
                  <field.SelectField items={methods.map((m) => ({ value: m, label: m }))} />
                </field.Field>
              )}
            </form.AppField>

            <form.AppField name="interval">
              {(field) => (
                <field.Field label="interval" serverError={getServerError('interval')}>
                  <field.SelectField
                    items={intervals.map((i) => ({
                      value: i,
                      label: INTERVAL_LABELS[i] || `${i}s`,
                    }))}
                  />
                </field.Field>
              )}
            </form.AppField>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <form.AppField name="timeout">
              {(field) => (
                <field.Field label="timeout (seconds)" serverError={getServerError('timeout')}>
                  <field.NumberInput min={5} max={120} />
                </field.Field>
              )}
            </form.AppField>

            <form.AppField name="expected_status_code">
              {(field) => (
                <field.Field
                  label="status code"
                  serverError={getServerError('expected_status_code')}
                >
                  <field.NumberInput min={100} max={599} />
                </field.Field>
              )}
            </form.AppField>
          </div>

          {notifiers.length > 0 ? (
            <form.AppField name="notifiers">
              {(field) => (
                <field.Field label="notifiers" serverError={getServerError('notifiers')}>
                  <field.ComboboxField
                    items={notifiers.map((n) => ({
                      id: n.id,
                      label: n.name,
                      description: n.type,
                    }))}
                    placeholder="select notifiers..."
                    emptyMessage="no notifiers found."
                  />
                </field.Field>
              )}
            </form.AppField>
          ) : null}

          <form.AppField name="is_active">
            {(field) => <field.CheckboxField label="active" />}
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
