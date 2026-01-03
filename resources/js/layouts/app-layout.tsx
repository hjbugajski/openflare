import type { PropsWithChildren } from 'react';

import { Navigation } from '@/components/navigation';
import { cn } from '@/lib/cn';

interface AppLayoutProps extends PropsWithChildren {
  size?: 'sm' | 'default';
}

export default function AppLayout({ children, size = 'default' }: AppLayoutProps) {
  return (
    <div className="flex w-full flex-col">
      <Navigation />
      <main
        className={cn('mx-auto mt-12 flex w-full flex-col gap-4 p-4', {
          'max-w-2xl': size === 'sm',
          'max-w-6xl': size === 'default',
        })}
      >
        {children}
      </main>
    </div>
  );
}
