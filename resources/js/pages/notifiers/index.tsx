import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';

import { ServerDataTable } from '@/components/server-data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Heading } from '@/components/ui/heading';
import { toast } from '@/components/ui/toast';
import AppLayout from '@/layouts/app-layout';
import { create, destroy, edit } from '@/routes/notifiers';
import { type Notifier, type Paginated } from '@/types';

interface Props {
  notifiers: Paginated<Notifier>;
  types: string[];
}

// Handle pattern for row actions: selection determines payload (notifier to delete)
const deleteDialog = Dialog.createHandle<Notifier>();

export default function NotifiersIndex({ notifiers }: Props) {
  const [isDeleting, setIsDeleting] = useState(false);

  const handleDeleteConfirm = (notifier: Notifier) => {
    setIsDeleting(true);
    router.delete(destroy(notifier.id).url, {
      onSuccess: () => {
        deleteDialog.close();
        toast.success({ title: 'notifier deleted' });
      },
      onFinish: () => {
        setIsDeleting(false);
      },
    });
  };

  // Columns inside component: needs handleToggle and deleteDialog for row actions
  const columns: ColumnDef<Notifier>[] = [
    {
      accessorKey: 'name',
      header: 'name',
      meta: { className: 'whitespace-nowrap font-medium' },
    },
    {
      id: 'status',
      accessorFn: (notifier) => notifier.is_active,
      header: 'status',
      cell: ({ row }) => (
        <Badge variant={row.original.is_active ? 'success' : 'secondary'}>
          {row.original.is_active ? 'active' : 'inactive'}
        </Badge>
      ),
      meta: { className: 'whitespace-nowrap' },
    },
    {
      accessorKey: 'type',
      header: 'type',
      cell: ({ row }) => (
        <Badge variant={row.original.type === 'discord' ? 'purple' : 'blue'}>
          {row.original.type}
        </Badge>
      ),
      meta: { className: 'whitespace-nowrap' },
    },
    {
      id: 'default',
      accessorFn: (notifier) => notifier.is_default,
      header: 'default',
      cell: ({ row }) => (row.original.is_default ? <Badge variant="secondary">yes</Badge> : null),
      meta: { className: 'whitespace-nowrap' },
    },
    {
      accessorKey: 'monitors_count',
      header: 'monitors',
      cell: ({ row }) => row.original.monitors_count ?? 0,
      meta: { className: 'whitespace-nowrap' },
    },
    {
      id: 'excluded',
      accessorFn: (notifier) => notifier.excluded_monitors_count ?? 0,
      header: 'excluded',
      cell: ({ row }) => row.original.excluded_monitors_count ?? 0,
      meta: { className: 'whitespace-nowrap w-full' },
    },
    {
      id: 'actions',
      enableSorting: false,
      cell: ({ row }) => (
        <div className="flex justify-end gap-2">
          <Button variant="secondary" size="sm" render={<Link href={edit(row.original.id).url} />}>
            edit
          </Button>
          <Dialog.Trigger
            handle={deleteDialog}
            payload={row.original}
            render={<Button variant="destructive" size="sm" />}
          >
            delete
          </Dialog.Trigger>
        </div>
      ),
    },
  ];

  return (
    <AppLayout>
      <Head title="Notifiers" />

      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-center gap-2">
          <Heading title="notifiers" />
          <Badge variant="secondary">{notifiers.total}</Badge>
        </div>
        <Button render={<Link href={create().url} />}>add notifier</Button>
      </div>

      {notifiers.total === 0 ? (
        <Card.Root>
          <EmptyState className="p-8" message="no notifiers configured" />
        </Card.Root>
      ) : (
        <Card.Root>
          <ServerDataTable
            columns={columns}
            paginated={notifiers}
            queryParam="page"
            initialSorting={[{ id: 'name', desc: false }]}
          />
        </Card.Root>
      )}

      <Dialog.Root handle={deleteDialog}>
        {({ payload }) => (
          <Dialog.Portal>
            <Dialog.Backdrop />
            <Dialog.Content className="border-t border-accent bg-background-secondary">
              <Dialog.Header>
                <Dialog.Title>delete notifier</Dialog.Title>
              </Dialog.Header>
              <Dialog.Body>
                <p>
                  are you sure you want to delete <strong>{payload?.name}</strong>? this will remove
                  it from all monitors.
                </p>
              </Dialog.Body>
              <Dialog.Footer>
                <Dialog.Close render={<Button variant="secondary" disabled={isDeleting} />}>
                  cancel
                </Dialog.Close>
                <Button
                  variant="destructive"
                  disabled={isDeleting}
                  onClick={() => payload && handleDeleteConfirm(payload)}
                >
                  {isDeleting ? 'deleting...' : 'delete'}
                </Button>
              </Dialog.Footer>
            </Dialog.Content>
          </Dialog.Portal>
        )}
      </Dialog.Root>
    </AppLayout>
  );
}
