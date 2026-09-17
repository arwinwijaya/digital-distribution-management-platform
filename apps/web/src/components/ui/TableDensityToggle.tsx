import type { TableDensity } from '@/lib/admin-table';

interface TableDensityToggleProps {
  value: TableDensity;
  onChange: (density: TableDensity) => void;
}

const OPTIONS: { value: TableDensity; label: string }[] = [
  { value: 'compact', label: 'Compact' },
  { value: 'default', label: 'Default' },
  { value: 'comfortable', label: 'Comfortable' },
];

export default function TableDensityToggle({ value, onChange }: TableDensityToggleProps) {
  return (
    <div
      role="group"
      aria-label="Table density"
      className="inline-flex items-center rounded-lg border border-gray-200 bg-white p-0.5 shadow-sm"
    >
      {OPTIONS.map((option) => {
        const selected = option.value === value;
        return (
          <button
            key={option.value}
            type="button"
            aria-pressed={selected}
            onClick={() => onChange(option.value)}
            className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 ${
              selected
                ? 'bg-primary-600 text-white shadow-sm'
                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
            }`}
          >
            {option.label}
          </button>
        );
      })}
    </div>
  );
}
