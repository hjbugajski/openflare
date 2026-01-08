import { useState } from 'react';

import { router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';

import { IconClose } from '@/components/icons/close';
import { ServerDataTable } from '@/components/server-data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmDeleteDialog } from '@/components/ui/confirm-delete-dialog';
import { toast } from '@/components/ui/toast';
import { detach } from '@/routes/monitors/notifiers';
import type { NotifierSummary, Paginated } from '@/types';

interface NotifiersTableProps {
  monitorId: string;
  notifiers: Paginated<NotifierSummary>;
}

export function NotifiersTable({ monitorId, notifiers }: NotifiersTableProps) {
  const [notifierToRemove, setNotifierToRemove] = useState<NotifierSummary | null>(null);

  const handleDetach = () =>
    new Promise<void>((resolve) => {
      if (!notifierToRemove) {
        resolve();
        return;
      }

      router.delete(detach({ monitor: monitorId, notifier: notifierToRemove.id }).url, {
        preserveScroll: true,
        onSuccess: () => {
          toast.success({ title: 'notifier disabled' });
        },
        onFinish: () => {
          setNotifierToRemove(null);
          resolve();
        },
      });
    });

  // Columns inside component: needs setNotifierToRemove state setter for row actions
  const columns: ColumnDef<NotifierSummary>[] = [
    {
      accessorKey: 'name',
      header: 'name',
      meta: {
        className: 'whitespace-nowrap',
      },
    },
    {
      accessorKey: 'is_active',
      header: 'status',
      cell: ({ row }) => (
        <Badge variant={row.original.is_active ? 'success' : 'secondary'}>
          {row.original.is_active ? 'active' : 'inactive'}
        </Badge>
      ),
      meta: {
        className: 'whitespace-nowrap',
      },
    },
    {
      accessorKey: 'type',
      header: 'type',
      cell: ({ row }) => (
        <Badge variant={row.original.type === 'discord' ? 'purple' : 'blue'}>
          {row.original.type}
        </Badge>
      ),
      meta: {
        className: 'whitespace-nowrap',
      },
    },
    {
      accessorKey: 'apply_to_all',
      header: 'applied via',
      cell: ({ row }) =>
        row.original.apply_to_all ? (
          <Badge variant="cyan">automatic selection</Badge>
        ) : (
          <Badge variant="magenta">manual selection</Badge>
        ),
      meta: {
        className: 'whitespace-nowrap w-full',
      },
    },
    {
      id: 'actions',
      header: '',
      cell: ({ row }) => (
        <Button variant="tertiary" size="icon" onClick={() => setNotifierToRemove(row.original)}>
          <span className="sr-only">remove</span>
          <IconClose className="h-4 w-4" />
        </Button>
      ),
      meta: {
        className: 'text-right w-10',
      },
    },
  ];

  return (
    <>
      <ConfirmDeleteDialog
        open={notifierToRemove !== null}
        title="disable notifier"
        confirmLabel="disable"
        confirmingLabel="disabling..."
        onConfirm={handleDetach}
        onOpenChange={(open) => !open && setNotifierToRemove(null)}
      >
        <p>
          are you sure you want to disable <strong>{notifierToRemove?.name}</strong> for this
          monitor?
        </p>
      </ConfirmDeleteDialog>

      <ServerDataTable
        columns={columns}
        paginated={notifiers}
        queryParam="notifiers_page"
        reloadOnly={['notifiers']}
      />
    </>
  );
}
