import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useInertiaAppForm } from '@/components/ui/form/use-inertia-app-form';

const visitMock = vi.fn();
const pageProps: { errors: Record<string, string> } = { errors: {} };

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: pageProps }),
  router: { visit: (...args: unknown[]) => visitMock(...args) },
}));

describe('useInertiaAppForm', () => {
  beforeEach(() => {
    visitMock.mockReset();
    pageProps.errors = {};
  });

  it('keeps isSubmitting true until the visit settles and ignores a submit while in flight', async () => {
    let finishVisit = () => {};
    visitMock.mockImplementation((_action: string, options: { onFinish: () => void }) => {
      finishVisit = options.onFinish;
    });

    const { result } = renderHook(() =>
      useInertiaAppForm({ defaultValues: { name: '' }, action: '/x' }),
    );

    act(() => {
      void result.current.form.handleSubmit();
    });

    await waitFor(() => expect(result.current.form.state.isSubmitting).toBe(true));

    act(() => {
      void result.current.form.handleSubmit();
    });

    expect(visitMock).toHaveBeenCalledTimes(1);
    expect(result.current.form.state.isSubmitting).toBe(true);

    act(() => {
      finishVisit();
    });

    await waitFor(() => expect(result.current.form.state.isSubmitting).toBe(false));
  });

  describe('getServerError', () => {
    function renderGetServerError(errors: Record<string, string>) {
      pageProps.errors = errors;

      const { result } = renderHook(() =>
        useInertiaAppForm({ defaultValues: { notifiers: '' }, action: '/x' }),
      );

      return result.current.getServerError;
    }

    it('surfaces an array-element error under the field key', () => {
      const getServerError = renderGetServerError({ 'notifiers.0': 'msg' });

      expect(getServerError('notifiers')).toBe('msg');
    });

    it('prefers an exact match over an array-element error', () => {
      const getServerError = renderGetServerError({
        notifiers: 'exact',
        'notifiers.0': 'nested',
      });

      expect(getServerError('notifiers')).toBe('exact');
    });

    it('does not match a field whose key is only a prefix of another', () => {
      const getServerError = renderGetServerError({ notifiers_enabled: 'msg' });

      expect(getServerError('notifiers')).toBeUndefined();
    });
  });
});
