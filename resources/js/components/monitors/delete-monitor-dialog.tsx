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
  const handleDelete = () =>
    new Promise<void>((resolve) => {
      router.delete(destroy(monitorId).url, {
        onSuccess: () => {
          toast.success({ title: 'monitor deleted' });
        },
        onFinish: () => resolve(),
      });
    });

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
