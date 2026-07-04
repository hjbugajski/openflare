import { useCallback } from 'react';

import { router } from '@inertiajs/react';

import { ConfirmDeleteDialog } from '@/components/ui/confirm-delete-dialog';
import { toast } from '@/components/ui/toast';
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
      new Promise<void>((resolve, reject) => {
        let settled = false;
        router.delete(destroy(monitorId).url, {
          onSuccess: () => {
            toast.success({ title: 'monitor deleted' });
            settled = true;
            resolve();
          },
          onError: () => {
            toast.destructive({ title: 'failed to delete monitor' });
            settled = true;
            reject(new Error('failed to delete monitor'));
          },
          onFinish: () => {
            if (!settled) {
              toast.destructive({ title: 'failed to delete monitor' });
              reject(new Error('failed to delete monitor'));
            }
          },
        });
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
