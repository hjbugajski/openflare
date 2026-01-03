import { useCallback, useState } from 'react';

import { toast } from '@/components/ui/toast';
import { apiFetch } from '@/lib/http/api-fetch';
import { type NotifierConfig, validateNotifierConfig } from '@/lib/schemas/notifier';
import { test } from '@/routes/notifiers';

interface TestResponse {
  success: boolean;
  error?: string;
}

interface UseNotifierTestOptions {
  getType: () => string;
  getConfig: () => NotifierConfig;
}

export function useNotifierTest({ getType, getConfig }: UseNotifierTestOptions) {
  const [isTesting, setIsTesting] = useState(false);

  const handleTest = useCallback(async () => {
    const type = getType();
    const config = getConfig();

    const validation = validateNotifierConfig(type, config);
    if (!validation.valid) {
      toast.destructive({ title: validation.message });
      return;
    }

    setIsTesting(true);

    try {
      const { ok, data } = await apiFetch<TestResponse>(test().url, {
        method: 'POST',
        body: { type, config },
      });

      if (ok && data?.success) {
        toast.success({ title: 'test notification sent' });
      } else {
        toast.destructive({ title: data?.error || 'failed to send test' });
      }
    } catch {
      toast.destructive({ title: 'failed to send test notification' });
    } finally {
      setIsTesting(false);
    }
  }, [getType, getConfig]);

  return {
    isTesting,
    handleTest,
  };
}
