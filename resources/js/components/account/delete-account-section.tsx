import { useCallback, useState } from 'react';

import { router } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Heading } from '@/components/ui/heading';
import { Input } from '@/components/ui/input';
import { toast } from '@/components/ui/toast';
import { destroy } from '@/routes/settings';

export function DeleteAccountSection() {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [password, setPassword] = useState('');
  const [isDeleting, setIsDeleting] = useState(false);

  const handleDelete = useCallback(() => {
    setIsDeleting(true);
    router.delete(destroy().url, {
      data: { password },
      onSuccess: () => setDialogOpen(false),
      onError: (errors) => {
        toast.destructive({ title: errors.password || 'invalid password' });
      },
      onFinish: () => setIsDeleting(false),
    });
  }, [password]);

  const openDialog = useCallback(() => setDialogOpen(true), []);

  const handleOpenChange = useCallback((open: boolean) => {
    setDialogOpen(open);
    if (!open) {
      setPassword('');
    }
  }, []);

  const handlePasswordChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => setPassword(e.target.value),
    [],
  );

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent<HTMLInputElement>) => {
      if (e.key === 'Enter' && password) {
        handleDelete();
      }
    },
    [password, handleDelete],
  );

  return (
    <>
      <Card.Root>
        <Card.Header>
          <Heading
            level={2}
            title="Delete account"
            description="permanently delete your account and all data"
          />
        </Card.Header>
        <Card.Content className="space-y-4">
          <p>
            once your account is deleted, all of its resources and data will be permanently deleted.
          </p>
        </Card.Content>
        <Card.Footer className="justify-end">
          <Button variant="destructive" onClick={openDialog}>
            delete account
          </Button>
        </Card.Footer>
      </Card.Root>

      <Dialog.Root open={dialogOpen} onOpenChange={handleOpenChange}>
        <Dialog.Portal>
          <Dialog.Backdrop />
          <Dialog.Content>
            <Dialog.Header>
              <Dialog.Title>delete account</Dialog.Title>
            </Dialog.Header>
            <Dialog.Body>
              <p>this action cannot be undone. enter your password to confirm.</p>
              <Field label="password">
                <Input
                  autoFocus
                  type="password"
                  autoComplete="current-password"
                  value={password}
                  className="z-5"
                  onChange={handlePasswordChange}
                  onKeyDown={handleKeyDown}
                />
              </Field>
            </Dialog.Body>
            <Dialog.Footer>
              <Dialog.Close render={<Button variant="secondary" />}>cancel</Dialog.Close>
              <Button
                variant="destructive"
                disabled={!password || isDeleting}
                onClick={handleDelete}
              >
                {isDeleting ? 'deleting...' : 'delete account'}
              </Button>
            </Dialog.Footer>
          </Dialog.Content>
        </Dialog.Portal>
      </Dialog.Root>
    </>
  );
}
