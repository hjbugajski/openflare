import type { ColumnDef } from '@tanstack/react-table';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ServerDataTable } from '@/components/server-data-table';
import type { CursorPaginated } from '@/types';

const visit = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
  router: { visit },
}));

interface Row {
  id: string;
  name: string;
}

const columns: ColumnDef<Row>[] = [{ accessorKey: 'name', header: 'name' }];

function page(names: string[], overrides: Partial<CursorPaginated<Row>> = {}): CursorPaginated<Row> {
  return {
    data: names.map((name) => ({ id: name, name })),
    per_page: 2,
    next_cursor: null,
    prev_cursor: null,
    next_page_url: null,
    prev_page_url: null,
    total: 4,
    ...overrides,
  };
}

describe('ServerDataTable', () => {
  beforeEach(() => {
    visit.mockClear();
  });

  it('renders new rows when the paginated prop changes', () => {
    const { rerender } = render(
      <ServerDataTable columns={columns} paginated={page(['alpha', 'bravo'], { next_cursor: 'c1' })} />,
    );

    expect(screen.getByText('alpha')).toBeInTheDocument();
    expect(screen.getByText('bravo')).toBeInTheDocument();

    rerender(
      <ServerDataTable columns={columns} paginated={page(['charlie', 'delta'], { prev_cursor: 'c0' })} />,
    );

    expect(screen.getByText('charlie')).toBeInTheDocument();
    expect(screen.getByText('delta')).toBeInTheDocument();
    expect(screen.queryByText('alpha')).not.toBeInTheDocument();
  });

  it('requests the next page with the next cursor', () => {
    render(
      <ServerDataTable
        columns={columns}
        paginated={page(['alpha', 'bravo'], { next_cursor: 'c1' })}
        cursorParam="checks_cursor"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'next' }));

    expect(visit).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({ data: { checks_cursor: 'c1' } }),
    );
  });

  it('disables previous on the first page and next on the last', () => {
    render(<ServerDataTable columns={columns} paginated={page(['alpha', 'bravo'], { next_cursor: 'c1' })} />);

    expect(screen.getByRole('button', { name: 'previous' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'next' })).toBeEnabled();
  });
});
