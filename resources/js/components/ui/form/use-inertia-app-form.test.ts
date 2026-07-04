import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useInertiaAppForm } from '@/components/ui/form/use-inertia-app-form';

const visitMock = vi.fn();

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { errors: {} } }),
  router: { visit: (...args: unknown[]) => visitMock(...args) },
}));

describe('useInertiaAppForm', () => {
  beforeEach(() => {
    visitMock.mockReset();
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
});
