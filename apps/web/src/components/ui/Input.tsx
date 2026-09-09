import { InputHTMLAttributes } from 'react';

type Props = InputHTMLAttributes<HTMLInputElement> & {
  label?: string;
  error?: string;
};

export default function Input({ label, error, id, className = '', ...rest }: Props) {
  const inputId = id ?? `input-${Math.random().toString(36).slice(2)}`;
  return (
    <div className="w-full">
      {label && (
        <label htmlFor={inputId} className="block text-sm font-medium text-gray-700 mb-1">
          {label}
        </label>
      )}
      <input
        id={inputId}
        className={`block w-full rounded-lg border ${error ? 'border-danger-500' : 'border-gray-200'} bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed ${className}`}
        {...rest}
      />
      {error && <p className="mt-1 text-xs text-danger-600">{error}</p>}
    </div>
  );
}
