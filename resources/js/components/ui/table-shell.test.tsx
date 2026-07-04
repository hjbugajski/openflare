import type { ColumnDef } from '@tanstack/react-table';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { DataTable } from '@/components/ui/data-table';

interface Row {
  id: string;
  name: string;
}

const columns: ColumnDef<Row>[] = [{ accessorKey: 'name', header: 'name' }];

const rows: Row[] = [
  { id: '1', name: 'bravo' },
  { id: '2', name: 'alpha' },
];

const emptyRows: Row[] = [];

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
});
