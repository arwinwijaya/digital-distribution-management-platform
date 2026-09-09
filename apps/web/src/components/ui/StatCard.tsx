import { ReactNode } from 'react';

type Props = {
  label: string;
  value: string | number;
  change?: string;
  changeType?: 'up' | 'down' | 'neutral';
  icon?: ReactNode;
};

export default function StatCard({ label, value, change, changeType = 'neutral', icon }: Props) {
  const changeColors = {
    up:      'text-success-600 bg-success-50',
    down:    'text-danger-600 bg-danger-50',
    neutral: 'text-gray-500 bg-gray-50',
  };

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-card p-5 hover:shadow-card-hover transition-shadow duration-200">
      <div className="flex items-center justify-between mb-3">
        <span className="text-sm font-medium text-gray-500">{label}</span>
        {icon && <div className="w-9 h-9 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center text-lg">{icon}</div>}
      </div>
      <p className="text-2xl font-semibold text-gray-900">{value}</p>
      {change && (
        <span className={`inline-block mt-2 text-xs font-medium px-2 py-0.5 rounded-full ${changeColors[changeType]}`}>
          {change}
        </span>
      )}
    </div>
  );
}
