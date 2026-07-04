import { useCallback } from 'react';

import { ConfirmDeleteDialog } from '@/components/ui/confirm-delete-dialog';
import { inertiaDelete } from '@/lib/http/inertia-delete';
import { destroy } from '@/routes/monitors';

interface DeleteMonitorDialogProps {
  monitorId: string;
  monitorName: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function DeleteMonitorDialog({
  monitorId,
  monitorName,
  open,
  onOpenChange,
}: DeleteMonitorDialogProps) {
  const handleDelete = useCallback(
    () =>
      inertiaDelete(destroy(monitorId).url, {
        successTitle: 'monitor deleted',
        errorTitle: 'failed to delete monitor',
      }),
    [monitorId],
  );

  return (
    <ConfirmDeleteDialog
      open={open}
      title="delete monitor"
      onConfirm={handleDelete}
      onOpenChange={onOpenChange}
    >
      <p>
        are you sure you want to delete <strong>{monitorName}</strong>? this action cannot be
        undone.
      </p>
    </ConfirmDeleteDialog>
  );
}
