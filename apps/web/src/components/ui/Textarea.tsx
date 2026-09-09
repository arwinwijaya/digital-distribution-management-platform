import { TextareaHTMLAttributes } from 'react';

type Props = TextareaHTMLAttributes<HTMLTextAreaElement> & {
  label?: string;
};

export default function Textarea({ label, id, className = '', ...rest }: Props) {
  const inputId = id ?? `textarea-${Math.random().toString(36).slice(2)}`;
  return (
    <div className="w-full">
      {label && (
        <label htmlFor={inputId} className="block text-sm font-medium text-gray-700 mb-1">
          {label}
        </label>
      )}
      <textarea
        id={inputId}
        rows={4}
        className={`block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed resize-y ${className}`}
        {...rest}
      />
    </div>
  );
}
