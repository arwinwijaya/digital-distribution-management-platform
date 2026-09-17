import { buildCountLabel } from '@/lib/admin-table';

interface TablePaginationProps {
  cursor: number;
  limit: number;
  total?: number;
  hasMore: boolean;
  onPageChange: (newCursor: number) => void;
}

export default function TablePagination({
  cursor,
  limit,
  total,
  hasMore,
  onPageChange,
}: TablePaginationProps) {
  const isBeyondTotal = total !== undefined && cursor >= total;

  const isPrevDisabled = cursor === 0;
  const isNextDisabled = !hasMore || (total !== undefined && cursor + limit >= total) || isBeyondTotal;

  const handlePrev = () => {
    if (!isPrevDisabled) {
      onPageChange(cursor - limit);
    }
  };

  const handleNext = () => {
    if (!isNextDisabled) {
      onPageChange(cursor + limit);
    }
  };

  const handleJump = (pageIndex: number) => {
    onPageChange(pageIndex * limit);
  };

  // Calculate total pages for jump buttons
  const estimatedTotal = total ?? (cursor + limit + (hasMore ? 1 : 0));
  const totalPages = Math.max(1, Math.ceil(estimatedTotal / limit));
  const currentPage = Math.floor(cursor / limit);

  const label = buildCountLabel({ total, cursor, limit, hasMore });

  return (
    <div className="flex flex-col sm:flex-row items-center justify-between gap-4 px-5 py-4 bg-white border-t border-gray-100">
      <div className="text-sm text-gray-600">
        {isBeyondTotal ? (
          <span className="text-gray-500">tidak ada data lanjutan</span>
        ) : (
          label
        )}
      </div>

      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={handlePrev}
          disabled={isPrevDisabled}
          className="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          Sebelumnya
        </button>

        <div className="flex items-center gap-1">
          {Array.from({ length: totalPages }, (_, i) => (
            <button
              key={i}
              type="button"
              onClick={() => handleJump(i)}
              className={`min-w-[2rem] px-2 py-1.5 text-sm font-medium rounded-lg transition-colors ${
                i === currentPage
                  ? 'bg-primary-600 text-white'
                  : 'text-gray-700 bg-white border border-gray-200 hover:bg-gray-50'
              }`}
            >
              {i + 1}
            </button>
          ))}
        </div>

        <button
          type="button"
          onClick={handleNext}
          disabled={isNextDisabled}
          className="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          Berikutnya
        </button>
      </div>
    </div>
  );
}
