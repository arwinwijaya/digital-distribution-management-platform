import { KeyboardEvent, ReactNode } from 'react';
import type { ColumnSort, TableDensity } from '@/lib/admin-table';

type Column<T> = {
  key: string;
  header: string;
  render: (row: T) => ReactNode;
  className?: string;
};

type Props<T> = {
  columns: Column<T>[];
  rows: T[];
  rowKey: (row: T) => string | number;
  empty?: ReactNode;
  /** Column keys whose headers toggle sorting. Anything absent stays INERT. */
  sortableColumns?: string[];
  /** Canonical sort state (`{ column, order }`) owned by the parent page. */
  sort?: ColumnSort;
  /** Called with the clicked column key; ignored for non-sortable columns. */
  onSort?: (column: string) => void;
  /** Row density bucket; defaults to `default` (the legacy padding). */
  density?: TableDensity;
};

/**
 * Density -> padding classes. `default` MUST stay byte-identical to the legacy
 * markup so the 6 not-yet-migrated admin pages render unchanged.
 */
const DENSITY_PADDING: Record<TableDensity, { th: string; td: string }> = {
  compact: { th: 'px-5 py-2', td: 'px-5 py-2' },
  default: { th: 'px-5 py-3', td: 'px-5 py-3.5' },
  comfortable: { th: 'px-5 py-4', td: 'px-5 py-5' },
};

/** Map the canonical `SortOrder` to the exact `aria-sort` token. */
function ariaSortValue(sort: ColumnSort | undefined, columnKey: string): 'ascending' | 'descending' | 'none' {
  if (sort?.column !== columnKey) return 'none';
  return sort.order === 'asc' ? 'ascending' : 'descending';
}

/** Direction glyph shown next to a sortable header label. */
function SortIndicator({ ariaSort }: { ariaSort: 'ascending' | 'descending' | 'none' }) {
  const glyph = ariaSort === 'ascending' ? '↑' : ariaSort === 'descending' ? '↓' : '↕';
  return (
    <span aria-hidden="true" className={`ml-1 ${ariaSort === 'none' ? 'text-gray-300' : 'text-gray-700'}`}>
      {glyph}
    </span>
  );
}

export default function Table<T>({ columns, rows, rowKey, empty, sortableColumns, sort, onSort, density = 'default' }: Props<T>) {
  if (rows.length === 0) return <>{empty ?? <div className="p-8 text-center text-sm text-gray-500">Belum ada data.</div>}</>;

  const padding = DENSITY_PADDING[density] ?? DENSITY_PADDING.default;

  const handleHeaderKeyDown = (event: KeyboardEvent<HTMLTableCellElement>, columnKey: string) => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    event.preventDefault();
    onSort?.(columnKey);
  };

  return (
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-100 text-left text-sm">
        <thead className="bg-gray-50/80">
          <tr>
            {columns.map((column) => {
              const sortable = sortableColumns?.includes(column.key) ?? false;
              const headerSort = sortable ? ariaSortValue(sort, column.key) : undefined;
              return (
                <th
                  key={column.key}
                  aria-sort={headerSort}
                  tabIndex={sortable ? 0 : undefined}
                  onClick={sortable ? () => onSort?.(column.key) : undefined}
                  onKeyDown={sortable ? (event) => handleHeaderKeyDown(event, column.key) : undefined}
                  className={`${padding.th} text-xs font-semibold uppercase tracking-wide text-gray-500 ${sortable ? 'cursor-pointer select-none hover:text-gray-700' : ''} ${column.className ?? ''}`}
                >
                  {column.header}
                  {headerSort ? <SortIndicator ariaSort={headerSort} /> : null}
                </th>
              );
            })}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100 bg-white">
          {rows.map((row) => (
            <tr key={rowKey(row)} className="transition-colors hover:bg-gray-50/70">
              {columns.map((column) => <td key={column.key} className={`${padding.td} text-gray-700 ${column.className ?? ''}`}>{column.render(row)}</td>)}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
