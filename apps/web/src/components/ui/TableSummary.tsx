interface TableSummaryProps {
  total: number;
  breakdown?: { label: string; value: number }[];
  noun: string;
}

export default function TableSummary({ total, breakdown, noun }: TableSummaryProps) {
  const parts: string[] = [`${total} ${noun}`];

  if (breakdown && breakdown.length > 0) {
    for (const item of breakdown) {
      parts.push(`${item.value} ${item.label}`);
    }
  }

  return (
    <div className="text-sm text-gray-600" data-testid="table-summary">
      {parts.join(' · ')}
    </div>
  );
}
