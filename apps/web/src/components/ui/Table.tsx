import { ReactNode } from 'react';

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
};

export default function Table<T>({ columns, rows, rowKey, empty }: Props<T>) {
  if (rows.length === 0) return <>{empty ?? <div className="p-8 text-center text-sm text-gray-500">Belum ada data.</div>}</>;
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-100 text-left text-sm">
        <thead className="bg-gray-50/80">
          <tr>
            {columns.map((column) => <th key={column.key} className={`px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 ${column.className ?? ''}`}>{column.header}</th>)}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100 bg-white">
          {rows.map((row) => (
            <tr key={rowKey(row)} className="transition-colors hover:bg-gray-50/70">
              {columns.map((column) => <td key={column.key} className={`px-5 py-3.5 text-gray-700 ${column.className ?? ''}`}>{column.render(row)}</td>)}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
