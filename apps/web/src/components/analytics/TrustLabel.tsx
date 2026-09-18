'use client';

import { trustLabel, methodLabel, measurementNote } from '@/app/analytics/presentation';

/**
 * Inline trust labels for the AI cards.
 *
 * Every field is optional: when a helper returns null the label is omitted
 * entirely, so absent metadata never renders "undefined" or an empty chip.
 */
export default function TrustLabel({
  level,
  method,
  measurement,
}: {
  level?: string | null;
  method?: string | null;
  measurement?: { measured?: boolean; note?: string } | null;
}) {
  const levelText = trustLabel(level);
  const methodText = methodLabel(method);
  const noteText = measurementNote(measurement);

  if (!levelText && !methodText && !noteText) return null;

  return (
    <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500">
      {levelText && (
        <span className="inline-flex items-center rounded-full bg-gray-50 px-2 py-0.5 font-medium text-gray-600">
          {levelText}
        </span>
      )}
      {methodText && <span>Metode: {methodText}</span>}
      {noteText && <span>{noteText}</span>}
    </div>
  );
}
