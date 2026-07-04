import { router } from '@inertiajs/react';

import { toast } from '@/components/ui/toast';

interface InertiaDeleteOptions {
  preserveScroll?: boolean;
  successTitle: string;
  errorTitle: string;
}

/**
 * Wraps `router.delete` in a promise that resolves on success and rejects on
 * failure, showing the appropriate toast either way.
 */
export function inertiaDelete(url: string, options: InertiaDeleteOptions): Promise<void> {
  const { preserveScroll, successTitle, errorTitle } = options;

  return new Promise<void>((resolve, reject) => {
    let settled = false;

    router.delete(url, {
      preserveScroll,
      onSuccess: () => {
        toast.success({ title: successTitle });
        settled = true;
        resolve();
      },
      onError: () => {
        toast.destructive({ title: errorTitle });
        settled = true;
        reject(new Error(errorTitle));
      },
      onFinish: () => {
        if (!settled) {
          toast.destructive({ title: errorTitle });
          reject(new Error(errorTitle));
        }
      },
    });
  });
}
