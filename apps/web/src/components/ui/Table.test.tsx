/**
 * Table.test.tsx — unit tests for the generic admin table shell.
 *
 * Level: unit component. The REAL <Table /> is the unit under test and is
 * never mocked; the surrounding sort state lives in the parent page and is
 * injected here as plain props (the canonical `ColumnSort` contract from
 * `@/lib/admin-table`).
 *
 * Cycle 1 — sortable headers: `sortableColumns` marks which headers are
 * clickable, `sort`/`onSort` drive the state, `aria-sort` reflects it
 * (`ascending` / `descending` / `none`) and non-sortable columns such as
 * "Aksi" stay completely INERT (no aria-sort, no click handler).
 *
 * Cycle 2 — density: `compact` / `default` / `comfortable` change the th/td
 * padding while the no-prop bucket stays byte-identical to the legacy markup.
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, fireEvent } from '@testing-library/react';
import Table from '@/components/ui/Table';

type Row = { id: number; name: string };

const columns = [
  { key: 'name', header: 'Nama', render: (row: Row) => row.name },
  { key: 'kode', header: 'Kode', render: (row: Row) => `K-${row.id}` },
  { key: 'aksi', header: 'Aksi', render: (row: Row) => <button type="button">Edit</button> },
];

const rows: Row[] = [
  { id: 1, name: 'Alpha' },
  { id: 2, name: 'Beta' },
];

const rowKey = (row: Row) => row.id;

/* ------------------------------------------------------------------ */
/* Cycle 1 — sortable header render + onSort + aria-sort               */
/* ------------------------------------------------------------------ */
describe('Cycle 1 — sortable headers, aria-sort and inert columns', () => {
  it('marks the active ascending sortable header with aria-sort="ascending" and an up-arrow indicator', () => {
    render(
      <Table
        columns={columns}
        rows={rows}
        rowKey={rowKey}
        sortableColumns={['name']}
        sort={{ column: 'name', order: 'asc' }}
        onSort={jest.fn()}
      />,
    );

    const nameHeader = screen.getByRole('columnheader', { name: /Nama/ });
    expect(nameHeader).toHaveAttribute('aria-sort', 'ascending');
    expect(nameHeader.textContent).toContain('↑');
  });

  it('marks the active descending sortable header with aria-sort="descending" and a down-arrow indicator', () => {
    render(
      <Table
        columns={columns}
        rows={rows}
        rowKey={rowKey}
        sortableColumns={['name']}
        sort={{ column: 'name', order: 'desc' }}
        onSort={jest.fn()}
      />,
    );

    const nameHeader = screen.getByRole('columnheader', { name: /Nama/ });
    expect(nameHeader).toHaveAttribute('aria-sort', 'descending');
    expect(nameHeader.textContent).toContain('↓');
  });

  it('renders aria-sort="none" on sortable headers that are not the active column, and no aria-sort on inert headers', () => {
    render(
      <Table
        columns={columns}
        rows={rows}
        rowKey={rowKey}
        sortableColumns={['name', 'kode']}
        sort={{ column: 'name', order: 'asc' }}
        onSort={jest.fn()}
      />,
    );

    expect(screen.getByRole('columnheader', { name: /Nama/ })).toHaveAttribute('aria-sort', 'ascending');
    expect(screen.getByRole('columnheader', { name: /Kode/ })).toHaveAttribute('aria-sort', 'none');
    // "Aksi" is not in sortableColumns → the attribute must be absent entirely.
    expect(screen.getByRole('columnheader', { name: /Aksi/ })).not.toHaveAttribute('aria-sort');
  });

  it('renders aria-sort="none" on every sortable header when no sort is active', () => {
    render(
      <Table
        columns={columns}
        rows={rows}
        rowKey={rowKey}
        sortableColumns={['name', 'kode']}
        onSort={jest.fn()}
      />,
    );

    expect(screen.getByRole('columnheader', { name: /Nama/ })).toHaveAttribute('aria-sort', 'none');
    expect(screen.getByRole('columnheader', { name: /Kode/ })).toHaveAttribute('aria-sort', 'none');
    expect(screen.getByRole('columnheader', { name: /Aksi/ })).not.toHaveAttribute('aria-sort');
  });

  it('calls onSort with the column key when a sortable header is clicked', () => {
    const onSort = jest.fn();

    render(
      <Table
        columns={columns}
        rows={rows}
        rowKey={rowKey}
        sortableColumns={['name', 'kode']}
        sort={{ column: 'name', order: 'asc' }}
        onSort={onSort}
      />,
    );

    fireEvent.click(screen.getByRole('columnheader', { name: /Nama/ }));
    expect(onSort).toHaveBeenCalledTimes(1);
    expect(onSort).toHaveBeenCalledWith('name');

    // A sortable-but-inactive column also reports its own key.
    fireEvent.click(screen.getByRole('columnheader', { name: /Kode/ }));
    expect(onSort).toHaveBeenCalledTimes(2);
    expect(onSort).toHaveBeenLastCalledWith('kode');
  });

  it('keeps non-sortable headers inert: clicking "Aksi" never calls onSort', () => {
    const onSort = jest.fn();

    render(
      <Table
        columns={columns}
        rows={rows}
        rowKey={rowKey}
        sortableColumns={['name']}
        sort={{ column: 'name', order: 'asc' }}
        onSort={onSort}
      />,
    );

    fireEvent.click(screen.getByRole('columnheader', { name: /Aksi/ }));
    expect(onSort).not.toHaveBeenCalled();
  });

  it('stays fully inert (no aria-sort, no onSort) when the sortableColumns prop is omitted', () => {
    const onSort = jest.fn();

    render(<Table columns={columns} rows={rows} rowKey={rowKey} onSort={onSort} />);

    const headers = screen.getAllByRole('columnheader');
    expect(headers).toHaveLength(3);
    headers.forEach((header) => expect(header).not.toHaveAttribute('aria-sort'));

    fireEvent.click(screen.getByRole('columnheader', { name: /Nama/ }));
    fireEvent.click(screen.getByRole('columnheader', { name: /Aksi/ }));
    expect(onSort).not.toHaveBeenCalled();
  });

  it('preserves the legacy contracts: rows rendered via rowKey + custom empty state without <thead>', () => {
    const { container, unmount } = render(<Table columns={columns} rows={rows} rowKey={rowKey} />);

    expect(screen.getByText('Alpha')).toBeInTheDocument();
    expect(screen.getByText('Beta')).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: /Nama/ })).toBeInTheDocument();
    unmount();

    const empty = render(
      <Table columns={columns} rows={[]} rowKey={rowKey} empty={<div>Kosong.</div>} />,
    );
    expect(empty.getByText('Kosong.')).toBeInTheDocument();
    expect(empty.container.querySelector('thead')).toBeNull();

    // Default empty copy when no `empty` prop is supplied.
    const fallback = render(<Table columns={columns} rows={[]} rowKey={rowKey} />);
    expect(fallback.getByText('Belum ada data.')).toBeInTheDocument();
    expect(fallback.container.querySelector('thead')).toBeNull();
    expect(container).toBeDefined();
  });
});

/* ------------------------------------------------------------------ */
/* Cycle 2 — density prop changes padding                              */
/* ------------------------------------------------------------------ */

/** Extract the numeric vertical padding (the `py-*` token) from a cell class. */
function paddingY(element: HTMLElement): number {
  const match = /py-(\d+(?:\.\d+)?)/.exec(element.className);
  return match ? Number.parseFloat(match[1]) : Number.NaN;
}

describe('Cycle 2 — density prop changes padding', () => {
  it('keeps the legacy default padding when no density prop is supplied', () => {
    render(<Table columns={columns} rows={rows} rowKey={rowKey} />);

    const th = screen.getAllByRole('columnheader')[0];
    const td = screen.getAllByRole('cell')[0];
    expect(th).toHaveClass('py-3');
    expect(td).toHaveClass('py-3.5');
  });

  it('shrinks th/td padding below the default for density="compact"', () => {
    render(<Table columns={columns} rows={rows} rowKey={rowKey} density="compact" />);

    const th = screen.getAllByRole('columnheader')[0];
    const td = screen.getAllByRole('cell')[0];
    expect(th).toHaveClass('py-2');
    expect(td).toHaveClass('py-2');
    expect(paddingY(th)).toBeLessThan(3);
    expect(paddingY(td)).toBeLessThan(3.5);
  });

  it('grows th/td padding above the default for density="comfortable"', () => {
    render(<Table columns={columns} rows={rows} rowKey={rowKey} density="comfortable" />);

    const th = screen.getAllByRole('columnheader')[0];
    const td = screen.getAllByRole('cell')[0];
    expect(th).toHaveClass('py-4');
    expect(td).toHaveClass('py-5');
    expect(paddingY(th)).toBeGreaterThan(3);
    expect(paddingY(td)).toBeGreaterThan(3.5);
  });

  it('renders an explicit density="default" identically to the no-prop bucket', () => {
    const { container: omitted } = render(<Table columns={columns} rows={rows} rowKey={rowKey} />);
    const omittedTh = omitted.querySelector('th')?.className ?? '';
    const omittedTd = omitted.querySelector('td')?.className ?? '';

    const { container: explicit } = render(
      <Table columns={columns} rows={rows} rowKey={rowKey} density="default" />,
    );
    const explicitTh = explicit.querySelector('th')?.className ?? '';
    const explicitTd = explicit.querySelector('td')?.className ?? '';

    expect(explicitTh).toBe(omittedTh);
    expect(explicitTd).toBe(omittedTd);
  });
});
