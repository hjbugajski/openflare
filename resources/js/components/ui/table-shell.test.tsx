import type { ColumnDef } from '@tanstack/react-table';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { DataTable } from '@/components/ui/data-table';
import type { TableFeatures } from '@/components/ui/table-features';

interface Row {
  id: string;
  name: string;
}

const columns: ColumnDef<TableFeatures, Row>[] = [{ accessorKey: 'name', header: 'name' }];

const rows: Row[] = [
  { id: '1', name: 'bravo' },
  { id: '2', name: 'alpha' },
];

const emptyRows: Row[] = [];

const unsortableColumns: ColumnDef<TableFeatures, Row>[] = [
  { accessorKey: 'name', header: 'name', enableSorting: false },
];

describe('TableShell (via DataTable)', () => {
  it('renders a row per data item', () => {
    render(<DataTable columns={columns} data={rows} />);

    expect(screen.getByText('bravo')).toBeInTheDocument();
    expect(screen.getByText('alpha')).toBeInTheDocument();
  });

  it('renders an empty state when there are no rows', () => {
    render(<DataTable columns={columns} data={emptyRows} />);

    expect(screen.getByText('no results')).toBeInTheDocument();
  });

  it('toggles sort order when a sortable header is clicked', () => {
    render(<DataTable columns={columns} data={rows} />);

    const getCellText = () => screen.getAllByRole('cell').map((cell) => cell.textContent);

    expect(getCellText()).toEqual(['bravo', 'alpha']);

    fireEvent.click(screen.getByRole('button', { name: 'name' }));
    expect(getCellText()).toEqual(['alpha', 'bravo']);

    fireEvent.click(screen.getByRole('button', { name: 'name' }));
    expect(getCellText()).toEqual(['bravo', 'alpha']);
  });

  it('reports the sort direction through aria-sort', () => {
    render(<DataTable columns={columns} data={rows} />);

    const header = () => screen.getByRole('columnheader');

    expect(header()).not.toHaveAttribute('aria-sort');

    fireEvent.click(screen.getByRole('button', { name: 'name' }));
    expect(header()).toHaveAttribute('aria-sort', 'ascending');

    fireEvent.click(screen.getByRole('button', { name: 'name' }));
    expect(header()).toHaveAttribute('aria-sort', 'descending');
  });

  it('omits aria-sort on columns that cannot be sorted', () => {
    render(<DataTable columns={unsortableColumns} data={rows} />);

    expect(screen.getByRole('columnheader')).not.toHaveAttribute('aria-sort');
  });
});
