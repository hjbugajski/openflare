import { useCallback, useMemo } from 'react';

import type { FormDataConvertible } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/react';
import { type StandardSchemaV1 } from '@tanstack/react-form';

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
  /** Maps form state to the request payload, for values that must not be sent verbatim. */
  transform?: (values: TData) => FormData;
  onSuccess?: () => void;
  onError?: () => void;
}

export function useInertiaAppForm<TData extends FormData>({
  defaultValues,
  action,
  method = 'post',
  validators,
  transform,
  onSuccess,
  onError,
}: UseInertiaFormOptions<TData>) {
  const pageProps = usePage().props;
  const serverErrors = useMemo(
    () => pageProps.errors as Record<string, string>,
    [pageProps.errors],
  );

  const form = useAppForm({
    defaultValues,
    validators,
    onSubmit: ({ value }: { value: TData }) => {
      return new Promise<void>((resolve) => {
        router.visit(action, {
          method,
          data: transform ? transform(value) : value,
          onSuccess: () => {
            onSuccess?.();
          },
          onError: () => {
            onError?.();
          },
          onFinish: () => {
            resolve();
          },
        });
      });
    },
  });

  /*
   * Laravel keys array-element failures by index (`notifiers.0`), which no
   * field asks for, so an exact miss falls back to the first error nested
   * under the field's own key.
   */
  const getServerError = useCallback(
    (fieldName: string): string | undefined => {
      const exact = serverErrors[fieldName];

      if (exact !== undefined) {
        return exact;
      }

      const nestedKey = Object.keys(serverErrors).find((key) => key.startsWith(`${fieldName}.`));

      return nestedKey === undefined ? undefined : serverErrors[nestedKey];
    },
    [serverErrors],
  );

  return {
    form,
    serverErrors,
    getServerError,
  };
}
