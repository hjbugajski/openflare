import type { PropsWithChildren } from 'react';

import { usePage } from '@inertiajs/react';

import { Navigation } from '@/components/navigation';
import { StatusToolbar } from '@/components/status-toolbar';
import { cn } from '@/lib/cn';
import type { PageProps } from '@/types';

interface AppLayoutProps extends PropsWithChildren {
  size?: 'sm' | 'default';
}

export default function AppLayout({ children, size = 'default' }: AppLayoutProps) {
  const { statusToolbar } = usePage<PageProps>().props;

  return (
    <div className="flex w-full flex-col">
      <Navigation />
      <main
        className={cn('mx-auto mt-12 flex w-full flex-col gap-4 p-4 pb-20', {
          'max-w-2xl': size === 'sm',
          'max-w-6xl': size === 'default',
        })}
      >
        {children}
      </main>
      <StatusToolbar summary={statusToolbar} size={size} />
    </div>
  );
}
