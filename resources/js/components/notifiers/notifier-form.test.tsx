import type { ReactNode } from 'react';

import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { NotifierForm, type NotifierFormValues } from '@/components/notifiers/notifier-form';
import type { MonitorSummary, NotifierType } from '@/types';

const { visit, page } = vi.hoisted(() => {
  const errors: Record<string, string> = {};

  return { visit: vi.fn(), page: { errors } };
});

vi.mock('@inertiajs/react', () => ({
  router: { visit },
  usePage: () => ({ props: page }),
  Link: ({ children, href }: { children: ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  ),
}));

const PARTIAL_WEBHOOK = 'https://discord.com/api/webhooks/partial';

const NO_MONITORS: MonitorSummary[] = [];

const TYPES: NotifierType[] = ['discord', 'email'];

const defaultValues: NotifierFormValues = {
  name: 'alerts',
  type: 'discord',
  config: { webhook_url: PARTIAL_WEBHOOK, email: '' },
  is_active: true,
  is_default: false,
  apply_to_existing: true,
  monitors: [],
  excluded_monitors: [],
};

function renderForm() {
  render(
    <NotifierForm
      defaultValues={defaultValues}
      monitors={NO_MONITORS}
      types={TYPES}
      action="/notifiers"
      method="post"
      submitLabel="create notifier"
      submittingLabel="creating..."
      cancelHref="/notifiers"
      initialMonitorMode="all"
    />,
  );
}

async function selectType(label: string, configLabel: RegExp) {
  fireEvent.click(screen.getByLabelText(/type/));

  const option = await screen.findByRole('option', { name: label });

  // Base UI only commits a mouse click that started on the item
  fireEvent.pointerDown(option);
  fireEvent.click(option);

  await screen.findByLabelText(configLabel);
}

function submittedData(): Record<string, unknown> {
  const call = visit.mock.calls[0];

  if (!call) {
    throw new Error('router.visit was not called');
  }

  return (call[1] as { data: Record<string, unknown> }).data;
}

describe('NotifierForm', () => {
  beforeEach(() => {
    visit.mockClear();
    page.errors = {};
  });

  it('submits only the active type config after switching from discord to email', async () => {
    renderForm();

    expect(screen.getByLabelText(/webhook URL/)).toHaveValue(PARTIAL_WEBHOOK);

    await selectType('email', /email address/);

    fireEvent.change(screen.getByLabelText(/email address/), {
      target: { value: 'alerts@example.com' },
    });

    fireEvent.click(screen.getByRole('button', { name: 'create notifier' }));

    await waitFor(() => expect(visit).toHaveBeenCalledTimes(1));

    expect(submittedData().config).toEqual({ email: 'alerts@example.com' });
    expect(submittedData().config).not.toHaveProperty('webhook_url');
  });

  it('renders a server error keyed to the active type field', async () => {
    page.errors = { 'config.email': 'An email address is required.' };

    renderForm();

    await selectType('email', /email address/);

    expect(screen.getByText('An email address is required.')).toBeInTheDocument();
  });
});
