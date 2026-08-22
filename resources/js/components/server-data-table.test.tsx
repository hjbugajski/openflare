import type { ColumnDef } from '@tanstack/react-table';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ServerDataTable } from '@/components/server-data-table';
import type { TableFeatures } from '@/components/ui/table-features';
import type { Paginated } from '@/types';

const visit = vi.hoisted(() => vi.fn());

/*
 * Inertia keeps `page.url` in sync with the address bar, so the mock reads it
 * back from `window.location` and `setUrl` stays the single lever for both.
 */
vi.mock('@inertiajs/react', () => ({
  router: { visit },
  usePage: () => ({ url: window.location.pathname + window.location.search }),
}));

interface Row {
  id: string;
  name: string;
}

const columns: ColumnDef<TableFeatures, Row>[] = [{ accessorKey: 'name', header: 'name' }];

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

function pageSelect() {
  return screen.getByRole('combobox', { name: 'go to page' });
}

async function selectPage(label: string) {
  fireEvent.click(pageSelect());

  const option = await screen.findByRole('option', { name: label });

  // Base UI only commits a mouse click that started on the item
  fireEvent.pointerDown(option);
  fireEvent.click(option);
}

function header() {
  return screen.getByRole('columnheader');
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

    expect(pageSelect()).toHaveTextContent('2');
    expect(screen.getByText('of 5')).toBeInTheDocument();

    // Repeated clicks without a server response must not desync the label.
    fireEvent.click(screen.getByRole('button', { name: 'next' }));
    fireEvent.click(screen.getByRole('button', { name: 'next' }));

    expect(pageSelect()).toHaveTextContent('2');

    rerender(
      <ServerDataTable
        columns={columns}
        paginated={page(['echo', 'foxtrot'], { current_page: 3, last_page: 5 })}
        {...params}
      />,
    );

    expect(pageSelect()).toHaveTextContent('3');
  });

  it('keeps the pager reachable when the page is past the last page', () => {
    setUrl('?checks_page=2');

    render(
      <ServerDataTable
        columns={columns}
        paginated={page([], { current_page: 2, last_page: 1, total: 2 })}
        {...params}
      />,
    );

    const previous = screen.getByRole('button', { name: 'previous' });

    expect(previous).toBeEnabled();
    // No item matches a page beyond the last one, so the trigger reads blank.
    expect(pageSelect()).toHaveTextContent(/^$/);

    fireEvent.click(previous);

    expect(visit).toHaveBeenCalledWith('/monitors/m1', expect.anything());
  });

  it('reads the sort direction back out of the url', () => {
    setUrl('?checks_sort=name&checks_direction=desc');

    render(<ServerDataTable columns={columns} paginated={page(['alpha', 'bravo'])} {...params} />);

    expect(header()).toHaveAttribute('aria-sort', 'descending');
  });

  it('forgets sorting when a navigation strips the sort params', () => {
    setUrl('?checks_sort=name&checks_direction=desc');

    const { rerender } = render(
      <ServerDataTable columns={columns} paginated={page(['alpha', 'bravo'])} {...params} />,
    );

    // A redirect after a non-GET request lands on the bare index.
    setUrl('');
    rerender(
      <ServerDataTable columns={columns} paginated={page(['alpha', 'bravo'])} {...params} />,
    );

    // The header icon is driven by the same value as `aria-sort`, so its
    // absence is also the neutral icon.
    expect(header()).not.toHaveAttribute('aria-sort');

    fireEvent.click(screen.getByRole('button', { name: 'next' }));

    expect(visitedSearch().has('checks_sort')).toBe(false);
    expect(visitedSearch().has('checks_direction')).toBe(false);
  });

  it('keeps a direction on the third header click instead of clearing the sort', () => {
    setUrl('?checks_sort=name&checks_direction=desc');

    render(<ServerDataTable columns={columns} paginated={page(['alpha', 'bravo'])} {...params} />);

    fireEvent.click(screen.getByRole('button', { name: 'name' }));

    expect(visitedSearch().get('checks_sort')).toBe('name');
    expect(visitedSearch().get('checks_direction')).toBe('asc');
  });

  it('jumps to the last page and back to the first, keeping the sort params', () => {
    setUrl('?checks_sort=name&checks_direction=desc');

    const { rerender } = render(
      <ServerDataTable
        columns={columns}
        paginated={page(['alpha', 'bravo'], { last_page: 5 })}
        {...params}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'last' }));

    expect(visitedSearch().get('checks_page')).toBe('5');
    expect(visitedSearch().get('checks_sort')).toBe('name');
    expect(visitedSearch().get('checks_direction')).toBe('desc');

    visit.mockClear();
    setUrl('?checks_sort=name&checks_direction=desc&checks_page=5');
    rerender(
      <ServerDataTable
        columns={columns}
        paginated={page(['yankee', 'zulu'], { current_page: 5, last_page: 5 })}
        {...params}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'first' }));

    expect(visitedSearch().has('checks_page')).toBe(false);
    expect(visitedSearch().get('checks_sort')).toBe('name');
  });

  it('disables the edge buttons at each end of the range', () => {
    const { rerender } = render(
      <ServerDataTable
        columns={columns}
        paginated={page(['alpha', 'bravo'], { last_page: 3 })}
        {...params}
      />,
    );

    expect(screen.getByRole('button', { name: 'first' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'last' })).toBeEnabled();

    rerender(
      <ServerDataTable
        columns={columns}
        paginated={page(['echo', 'foxtrot'], { current_page: 3, last_page: 3 })}
        {...params}
      />,
    );

    expect(screen.getByRole('button', { name: 'first' })).toBeEnabled();
    expect(screen.getByRole('button', { name: 'last' })).toBeDisabled();
  });

  it('jumps to a page picked from the select', async () => {
    render(
      <ServerDataTable
        columns={columns}
        paginated={page(['alpha', 'bravo'], { last_page: 4 })}
        {...params}
      />,
    );

    await selectPage('3');

    expect(visitedSearch().get('checks_page')).toBe('3');
  });

  it('disables the page select when there is only one page', () => {
    render(
      <ServerDataTable
        columns={columns}
        paginated={page(['alpha', 'bravo'], { last_page: 1, total: 2 })}
        {...params}
      />,
    );

    expect(pageSelect()).toBeDisabled();
  });
});
