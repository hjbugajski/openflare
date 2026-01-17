import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';

import { update } from '@/routes/settings/preferences';
import type { UserPreferences } from '@/types';

export function usePreferencePatch<K extends keyof UserPreferences>(
  key: K,
  initialValue: NonNullable<UserPreferences[K]>,
): [NonNullable<UserPreferences[K]>, (newValue: NonNullable<UserPreferences[K]>) => void] {
  const [value, setValue] = useState(initialValue);

  const patchValue = useCallback(
    (newValue: NonNullable<UserPreferences[K]>) => {
      if (newValue === value) return;

      setValue(newValue);
      router.patch(update().url, { [key]: newValue }, { preserveScroll: true });
    },
    [key, value],
  );

  return [value, patchValue];
}
