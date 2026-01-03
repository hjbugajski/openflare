import { type ReactNode, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';

interface ConfirmDeleteDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  children: ReactNode;
  onConfirm: () => Promise<void> | void;
  confirmLabel?: string;
  confirmingLabel?: string;
}

/**
 * Reusable confirmation dialog for delete/remove actions.
 *
 * Use this for simple confirmations where the parent already knows the entity.
 * For row actions where selection determines payload, use Dialog.createHandle pattern.
 */
export function ConfirmDeleteDialog({
  open,
  onOpenChange,
  title,
  children,
  onConfirm,
  confirmLabel = 'delete',
  confirmingLabel = 'deleting...',
}: ConfirmDeleteDialogProps) {
  const [isConfirming, setIsConfirming] = useState(false);

  const handleConfirm = async () => {
    setIsConfirming(true);
    try {
      await onConfirm();
      onOpenChange(false);
    } finally {
      setIsConfirming(false);
    }
  };

  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Portal>
        <Dialog.Backdrop />
        <Dialog.Content className="border-t border-accent bg-background-secondary">
          <Dialog.Header>
            <Dialog.Title>{title}</Dialog.Title>
          </Dialog.Header>
          <Dialog.Body>{children}</Dialog.Body>
          <Dialog.Footer>
            <Dialog.Close render={<Button variant="secondary" disabled={isConfirming} />}>
              cancel
            </Dialog.Close>
            <Button variant="destructive" disabled={isConfirming} onClick={handleConfirm}>
              {isConfirming ? confirmingLabel : confirmLabel}
            </Button>
          </Dialog.Footer>
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
