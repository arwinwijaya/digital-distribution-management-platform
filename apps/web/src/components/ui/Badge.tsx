import { ReactNode } from 'react';

type Props = {
  children: ReactNode;
  variant?: 'blue' | 'yellow' | 'green' | 'red' | 'gray';
};

const styles: Record<string, string> = {
  blue:   'bg-primary-50 text-primary-700 border-primary-200',
  yellow: 'bg-warning-50 text-warning-700 border-warning-200',
  green:  'bg-success-50 text-success-700 border-success-200',
  red:    'bg-danger-50 text-danger-700 border-danger-200',
  gray:   'bg-gray-50 text-gray-700 border-gray-200',
};

export default function Badge({ children, variant = 'blue' }: Props) {
  return (
    <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${styles[variant]}`}>
      {children}
    </span>
  );
}
