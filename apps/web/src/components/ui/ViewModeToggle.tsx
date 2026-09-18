import type { ViewMode } from '@/lib/product-filter';

interface ViewModeToggleProps {
  value: ViewMode;
  onChange: (mode: ViewMode) => void;
}

const OPTIONS: { value: ViewMode; label: string }[] = [
  { value: 'card', label: 'Card' },
  { value: 'table', label: 'Tabel' },
];

export default function ViewModeToggle({ value, onChange }: ViewModeToggleProps) {
  return (
    <div
      role="group"
      aria-label="Tampilan produk"
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
