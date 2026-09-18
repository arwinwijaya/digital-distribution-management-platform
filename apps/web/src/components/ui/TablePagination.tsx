import { buildCountLabel } from '@/lib/admin-table';

interface TablePaginationProps {
  cursor: number;
  limit: number;
  total?: number;
  hasMore: boolean;
  onPageChange: (newCursor: number) => void;
}

/**
 * Max page buttons rendered before the list is windowed with ellipses.
 * At or below this many pages EVERY page is shown (keeps small admin tables
 * unchanged); above it only first/last + current ±1 are shown so a large
 * dataset (e.g. 60 invoice pages) does not render 60 buttons.
 */
const MAX_VISIBLE_PAGES = 7;

/** 0-based page indices to render, with `'ellipsis'` for skipped ranges. */
function buildPageItems(totalPages: number, currentPage: number): Array<number | 'ellipsis'> {
  if (totalPages <= MAX_VISIBLE_PAGES) {
    return Array.from({ length: totalPages }, (_, i) => i);
  }

  const pages = [0, totalPages - 1, currentPage - 1, currentPage, currentPage + 1]
    .filter((page) => page >= 0 && page < totalPages)
    .filter((page, index, list) => list.indexOf(page) === index)
    .sort((a, b) => a - b);

  const items: Array<number | 'ellipsis'> = [];
  pages.forEach((page, index) => {
    if (index > 0 && page - pages[index - 1] > 1) items.push('ellipsis');
    items.push(page);
  });
  return items;
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
  const pageItems = buildPageItems(totalPages, currentPage);

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
          {pageItems.map((item, index) =>
            item === 'ellipsis' ? (
              <span
                key={`ellipsis-${index}`}
                aria-hidden="true"
                className="px-1 text-sm text-gray-400"
              >
                …
              </span>
            ) : (
              <button
                key={item}
                type="button"
                onClick={() => handleJump(item)}
                className={`min-w-[2rem] px-2 py-1.5 text-sm font-medium rounded-lg transition-colors ${
                  item === currentPage
                    ? 'bg-primary-600 text-white'
                    : 'text-gray-700 bg-white border border-gray-200 hover:bg-gray-50'
                }`}
              >
                {item + 1}
              </button>
            ),
          )}
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
