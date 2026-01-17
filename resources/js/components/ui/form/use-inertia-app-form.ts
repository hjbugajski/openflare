import type { FormDataConvertible } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/react';
import { type StandardSchemaV1 } from '@tanstack/react-form';
import { useCallback, useMemo } from 'react';

import { useAppForm } from '@/components/ui/form/create-form';

type InertiaMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

type FormData = Record<string, FormDataConvertible>;

interface UseInertiaFormOptions<TData extends FormData> {
  defaultValues: TData;
  action: string;
  method?: InertiaMethod;
  validators?: {
    onChange?: StandardSchemaV1<TData>;
    onBlur?: StandardSchemaV1<TData>;
    onSubmit?: StandardSchemaV1<TData>;
  };
  onSuccess?: () => void;
  onError?: () => void;
}

export function useInertiaAppForm<TData extends FormData>({
  defaultValues,
  action,
  method = 'post',
  validators,
  onSuccess,
  onError,
}: UseInertiaFormOptions<TData>) {
  const pageProps = usePage().props;
  const serverErrors = useMemo(
    () => (pageProps.errors ?? {}) as Record<string, string>,
    [pageProps.errors],
  );

  const form = useAppForm({
    defaultValues,
    validators,
    onSubmit: ({ value }: { value: TData }) => {
      router.visit(action, {
        method,
        data: value,
        onSuccess: () => {
          onSuccess?.();
        },
        onError: () => {
          onError?.();
        },
      });
    },
  });

  const getServerError = useCallback(
    (fieldName: string): string | undefined => {
      return serverErrors[fieldName];
    },
    [serverErrors],
  );

  return {
    form,
    serverErrors,
    getServerError,
  };
}
