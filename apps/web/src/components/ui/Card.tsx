import { ReactNode } from 'react';

type Props = {
  children: ReactNode;
  className?: string;
};

export default function Card({ children, className = '' }: Props) {
  return (
    <div className={`bg-white rounded-xl border border-gray-200 shadow-card ${className}`}>
      {children}
    </div>
  );
}
