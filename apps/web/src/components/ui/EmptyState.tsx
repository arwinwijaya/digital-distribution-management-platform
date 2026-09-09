import { ReactNode } from 'react';
import { ReactElement } from 'react';

type Props = {
  icon?: ReactElement;
  title: string;
  description: string;
  action?: ReactNode;
};

export default function EmptyState({ icon, title, description, action }: Props) {
  return (
    <div className="flex flex-col items-center justify-center py-16 text-center">
      {icon && <div className="mb-4 w-14 h-14 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-2xl">{icon}</div>}
      <h3 className="text-base font-semibold text-gray-900 mb-1">{title}</h3>
      <p className="text-sm text-gray-500 max-w-sm mb-5">{description}</p>
      {action}
    </div>
  );
}
