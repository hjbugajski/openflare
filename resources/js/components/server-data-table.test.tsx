import type { ColumnDef } from '@tanstack/react-table';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ServerDataTable } from '@/components/server-data-table';
import type { Paginated } from '@/types';

const visit = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
  router: { visit },
}));

interface Row {
  id: string;
  name: string;
}

const columns: ColumnDef<Row>[] = [{ accessorKey: 'name', header: 'name' }];

const params = {
  pageParam: 'checks_page',
  sortParam: 'checks_sort',
  directionParam: 'checks_direction',
};

function page(names: string[], overrides: Partial<Paginated<Row>> = {}): Paginated<Row> {
  return {
    data: names.map((name) => ({ id: name, name })),
    current_page: 1,
    last_page: 2,
    per_page: 2,
    total: 4,
    ...overrides,
  };
}

function setUrl(search: string) {
  window.history.replaceState({}, '', `/monitors/m1${search}`);
}

function firstVisit(): [string, Record<string, unknown>] {
  const call = visit.mock.calls[0];

  if (!call) {
    throw new Error('router.visit was not called');
  }

  return call as [string, Record<string, unknown>];
}

function visitedSearch() {
  return new URL(firstVisit()[0], 'http://localhost').searchParams;
}

describe('ServerDataTable', () => {
  beforeEach(() => {
    visit.mockClear();
    setUrl('');
  });

  it('renders new rows when the paginated prop changes', () => {
    const { rerender } = render(
      <ServerDataTable columns={columns} paginated={page(['alpha', 'bravo'])} {...params} />,
    );

    expect(screen.getByText('alpha')).toBeInTheDocument();
    expect(screen.getByText('bravo')).toBeInTheDocument();

    rerender(
      <ServerDataTable
        columns={columns}
        paginated={page(['charlie', 'delta'], { current_page: 2 })}
        {...params}
      />,
    );

    expect(screen.getByText('charlie')).toBeInTheDocument();
    expect(screen.getByText('delta')).toBeInTheDocument();
    expect(screen.queryByText('alpha')).not.toBeInTheDocument();
  });

  it('requests the next page through the url page param', () => {
    render(<ServerDataTable columns={columns} paginated={page(['alpha', 'bravo'])} {...params} />);

    fireEvent.click(screen.getByRole('button', { name: 'next' }));

    expect(visit).toHaveBeenCalledWith(
      '/monitors/m1?checks_page=2',
      expect.objectContaining({ preserveState: true, preserveScroll: true }),
    );
    expect(firstVisit()[1]).not.toHaveProperty('preserveUrl');
  });

  it('drops the page param when returning to the first page', () => {
    setUrl('?checks_page=2');

    render(
      <ServerDataTable
        columns={columns}
        paginated={page(['charlie', 'delta'], { current_page: 2 })}
        {...params}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'previous' }));

    expect(visit).toHaveBeenCalledWith('/monitors/m1', expect.anything());
  });

  it('leaves other tables params in the url untouched', () => {
    setUrl('?incidents_page=3&incidents_sort=cause');

    render(<ServerDataTable columns={columns} paginated={page(['alpha', 'bravo'])} {...params} />);

    fireEvent.click(screen.getByRole('button', { name: 'next' }));

    expect(visitedSearch().get('incidents_page')).toBe('3');
    expect(visitedSearch().get('incidents_sort')).toBe('cause');
    expect(visitedSearch().get('checks_page')).toBe('2');
  });

  it('resets to the first page when sorting changes', () => {
    setUrl('?checks_page=2');

    render(
      <ServerDataTable
        columns={columns}
        paginated={page(['charlie', 'delta'], { current_page: 2 })}
        {...params}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'name' }));

    expect(visitedSearch().has('checks_page')).toBe(false);
    expect(visitedSearch().get('checks_sort')).toBe('name');
    expect(visitedSearch().get('checks_direction')).toBe('asc');
  });

  it('disables previous on the first page', () => {
    render(<ServerDataTable columns={columns} paginated={page(['alpha', 'bravo'])} {...params} />);

    expect(screen.getByRole('button', { name: 'previous' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'next' })).toBeEnabled();
  });

  it('disables next on the last page', () => {
    render(
      <ServerDataTable
        columns={columns}
        paginated={page(['charlie', 'delta'], { current_page: 2 })}
        {...params}
      />,
    );

    expect(screen.getByRole('button', { name: 'previous' })).toBeEnabled();
    expect(screen.getByRole('button', { name: 'next' })).toBeDisabled();
  });

  it('labels the page from the server response only', () => {
    const { rerender } = render(
      <ServerDataTable
        columns={columns}
        paginated={page(['alpha', 'bravo'], { current_page: 2, last_page: 5 })}
        {...params}
      />,
    );

    expect(screen.getByText('page 2 of 5')).toBeInTheDocument();

    // Repeated clicks without a server response must not desync the label.
    fireEvent.click(screen.getByRole('button', { name: 'next' }));
    fireEvent.click(screen.getByRole('button', { name: 'next' }));

    expect(screen.getByText('page 2 of 5')).toBeInTheDocument();

    rerender(
      <ServerDataTable
        columns={columns}
        paginated={page(['echo', 'foxtrot'], { current_page: 3, last_page: 5 })}
        {...params}
      />,
    );

    expect(screen.getByText('page 3 of 5')).toBeInTheDocument();
  });
});
