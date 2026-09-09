import { ReactNode } from 'react';
import Button from './Button';

type Props = {
  open: boolean;
  onClose: () => void;
  title: string;
  children: ReactNode;
  footer?: ReactNode;
};

export default function Modal({ open, onClose, title, children, footer }: Props) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* backdrop */}
      <div className="fixed inset-0 bg-black/40" onClick={onClose} aria-hidden />
      {/* dialog */}
      <div className="relative z-10 w-full max-w-lg rounded-xl bg-white shadow-xl border border-gray-200 overflow-hidden">
        <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
          <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
          <Button variant="ghost" size="sm" onClick={onClose} aria-label="Tutup">✕</Button>
        </div>
        <div className="px-5 py-4 text-sm text-gray-700 max-h-[60vh] overflow-y-auto">{children}</div>
        {footer && (
          <div className="border-t border-gray-100 px-5 py-3 bg-gray-50 flex justify-end gap-2">
            {footer}
          </div>
        )}
      </div>
    </div>
  );
}
