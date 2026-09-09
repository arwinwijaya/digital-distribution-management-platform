import { SelectHTMLAttributes } from 'react';

type Props = SelectHTMLAttributes<HTMLSelectElement> & {
  label?: string;
};

export default function Select({ label, id, children, className = '', ...rest }: Props) {
  const inputId = id ?? `select-${Math.random().toString(36).slice(2)}`;
  return (
    <div className="w-full">
      {label && (
        <label htmlFor={inputId} className="block text-sm font-medium text-gray-700 mb-1">
          {label}
        </label>
      )}
      <select
        id={inputId}
        className={`block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed ${className}`}
        {...rest}
      >
        {children}
      </select>
    </div>
  );
}
