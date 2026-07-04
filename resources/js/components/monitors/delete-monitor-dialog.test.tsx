import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { DeleteMonitorDialog } from '@/components/monitors/delete-monitor-dialog';

const deleteMock = vi.fn();

vi.mock('@inertiajs/react', () => ({
  router: { delete: (...args: unknown[]) => deleteMock(...args) },
}));

function renderDialog() {
  const onOpenChange = vi.fn();

  render(
    <DeleteMonitorDialog monitorId="1" monitorName="example" open onOpenChange={onOpenChange} />,
  );

  return onOpenChange;
}

describe('DeleteMonitorDialog', () => {
  beforeEach(() => {
    deleteMock.mockReset();
  });

  it('stays open when the delete request fails', async () => {
    deleteMock.mockImplementation(
      (_url: string, options: { onError: () => void; onFinish: () => void }) => {
        options.onError();
        options.onFinish();
      },
    );

    const onOpenChange = renderDialog();

    fireEvent.click(screen.getByRole('button', { name: 'delete' }));

    await waitFor(() => expect(deleteMock).toHaveBeenCalledTimes(1));

    expect(onOpenChange).not.toHaveBeenCalledWith(false);
    expect(screen.getByRole('button', { name: 'delete' })).toBeInTheDocument();
  });

  it('closes the dialog when the delete request succeeds', async () => {
    deleteMock.mockImplementation((_url: string, options: { onSuccess: () => void }) => {
      options.onSuccess();
    });

    const onOpenChange = renderDialog();

    fireEvent.click(screen.getByRole('button', { name: 'delete' }));

    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false));
  });
});
