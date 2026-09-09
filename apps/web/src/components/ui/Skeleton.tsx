'use client';

import { ReactNode } from 'react';

type Props = { children: ReactNode };

export default function Skeleton({ children }: Props) {
  return <div className="skeleton h-4 w-full">{children}</div>;
}

export function CardSkeleton() {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <div className="skeleton h-4 w-1/3 rounded" />
      <div className="skeleton h-8 w-1/2 rounded" />
      <div className="skeleton h-3 w-2/3 rounded" />
    </div>
  );
}

export function TableSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div className="p-4 border-b border-gray-100 skeleton h-10 rounded" />
      <div className="divide-y divide-gray-100">
        {Array.from({ length: rows }).map((_, i) => (
          <div key={i} className="px-4 py-3 flex gap-4">
            <div className="skeleton h-4 w-1/4 rounded" />
            <div className="skeleton h-4 w-1/6 rounded" />
            <div className="skeleton h-4 w-1/6 rounded" />
            <div className="skeleton h-4 w-1/4 rounded" />
          </div>
        ))}
      </div>
    </div>
  );
}
