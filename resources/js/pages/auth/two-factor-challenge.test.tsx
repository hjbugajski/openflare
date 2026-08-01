import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import TwoFactorChallenge from '@/pages/auth/two-factor-challenge';

const { visit, page } = vi.hoisted(() => {
  const errors: Record<string, string> = {};

  return { visit: vi.fn(), page: { errors } };
});

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  router: { visit },
  usePage: () => ({ props: page }),
}));

/** The text of every element the input points at with `aria-describedby`. */
function describedText(input: HTMLElement): string {
  return (input.getAttribute('aria-describedby') ?? '')
    .split(/\s+/)
    .filter(Boolean)
    .map((id) => document.getElementById(id)?.textContent ?? '')
    .join(' ');
}

describe('TwoFactorChallenge', () => {
  beforeEach(() => {
    visit.mockClear();
  });

  it('does not submit when switching to the recovery code field', async () => {
    render(<TwoFactorChallenge />);

    fireEvent.change(screen.getByLabelText(/authentication code/), {
      target: { value: '123456' },
    });

    fireEvent.click(screen.getByRole('button', { name: 'use a recovery code' }));

    expect(await screen.findByLabelText(/recovery code/)).toBeInTheDocument();
    expect(visit).not.toHaveBeenCalled();
  });

  it('submits when Enter is pressed in the code field', async () => {
    render(<TwoFactorChallenge />);

    fireEvent.change(screen.getByLabelText(/authentication code/), {
      target: { value: '123456' },
    });

    fireEvent.keyDown(screen.getByLabelText(/authentication code/), { key: 'Enter' });

    await waitFor(() => expect(visit).toHaveBeenCalledTimes(1));

    expect(visit.mock.calls[0]?.[1]).toMatchObject({
      method: 'post',
      data: { code: '123456', recovery_code: '' },
    });
  });

  it('reports an empty authentication code next to the code field', async () => {
    render(<TwoFactorChallenge />);

    fireEvent.click(screen.getByRole('button', { name: 'verify' }));

    const input = screen.getByLabelText(/authentication code/);

    await waitFor(() => expect(describedText(input)).toContain('authentication code is required'));

    expect(visit).not.toHaveBeenCalled();
  });

  it('reports an empty recovery code next to the recovery field, then submits once filled', async () => {
    render(<TwoFactorChallenge />);

    fireEvent.click(screen.getByRole('button', { name: 'use a recovery code' }));
    fireEvent.click(screen.getByRole('button', { name: 'verify' }));

    const input = await screen.findByLabelText(/recovery code/);

    await waitFor(() => expect(describedText(input)).toContain('recovery code is required'));

    expect(visit).not.toHaveBeenCalled();

    fireEvent.change(input, { target: { value: 'abc-def' } });
    fireEvent.click(screen.getByRole('button', { name: 'verify' }));

    await waitFor(() => expect(visit).toHaveBeenCalledTimes(1));

    expect(visit.mock.calls[0]?.[1]).toMatchObject({
      method: 'post',
      data: { code: '', recovery_code: 'abc-def' },
    });
  });
});
