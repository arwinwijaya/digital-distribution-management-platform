import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Offline · DDP',
  description: 'Tidak ada koneksi internet.',
};

/**
 * Offline fallback page (Phase 8, T10).
 *
 * Served by the service worker when a navigation request fails while the device
 * is offline. Kept dependency-free so it renders from the precached shell.
 */
export default function OfflinePage() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center bg-gray-100 px-6 text-center">
      <div className="max-w-sm rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
        <span
          role="img"
          aria-label="offline"
          className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-danger-50 text-2xl text-danger-600"
        >
          ⚡
        </span>
        <h1 className="text-lg font-semibold text-gray-900">Anda sedang offline</h1>
        <p className="mt-2 text-sm text-gray-500">
          Koneksi internet tidak tersedia. Order yang Anda buat akan disimpan di perangkat dan
          dikirim otomatis begitu koneksi kembali.
        </p>
        <a
          href="/"
          className="mt-6 inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700"
        >
          Coba lagi
        </a>
      </div>
    </main>
  );
}
