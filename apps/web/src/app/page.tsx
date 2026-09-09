import Link from 'next/link';

const features = [
  { icon: '📦', title: 'Katalog terpusat', description: 'Kelola dan temukan produk dari supplier dalam satu platform.' },
  { icon: '🛒', title: 'Pesanan lebih mudah', description: 'Outlet dapat membuat, melacak, dan membayar pesanan dengan cepat.' },
  { icon: '📈', title: 'Insight bisnis', description: 'Pantau performa penjualan, outlet, pembayaran, dan distribusi.' },
];

export default function Home() {
  return (
    <main className="min-h-screen overflow-hidden bg-white">
      <header className="border-b border-gray-100 bg-white/90">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">
          <Link href="/" className="flex items-center gap-2.5">
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-600 font-bold text-white">D</span>
            <span className="font-semibold text-gray-900">Digital Distribution</span>
          </Link>
          <div className="flex items-center gap-3"><Link href="/dashboard" className="hidden text-sm font-medium text-gray-600 hover:text-gray-900 sm:inline">Masuk</Link><Link href="/outlets" className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700">Daftar outlet</Link></div>
        </div>
      </header>
      <section className="relative bg-gradient-to-br from-primary-50 via-white to-indigo-50"><div className="mx-auto grid max-w-7xl items-center gap-12 px-5 py-20 sm:px-8 lg:grid-cols-2 lg:py-28"><div><div className="mb-5 inline-flex items-center gap-2 rounded-full border border-primary-200 bg-white px-3 py-1 text-xs font-medium text-primary-700 shadow-sm"><span className="h-1.5 w-1.5 rounded-full bg-success-500" />Ekosistem distribusi FMCG digital</div><h1 className="max-w-xl text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">Distribusi lebih rapi, <span className="text-primary-600">bisnis lebih maju.</span></h1><p className="mt-5 max-w-lg text-lg leading-8 text-gray-600">Satu platform untuk menghubungkan supplier, outlet, sales, dan driver dalam operasional distribusi yang transparan.</p><div className="mt-8 flex flex-wrap gap-3"><Link href="/outlets" className="rounded-lg bg-primary-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-primary-600/20 transition hover:bg-primary-700">Mulai sebagai outlet →</Link><Link href="/dashboard" className="rounded-lg border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">Lihat dashboard</Link></div></div><div className="relative hidden lg:block"><div className="absolute -inset-4 rounded-3xl bg-primary-200/30 blur-2xl" /><div className="relative rounded-2xl border border-white/80 bg-white p-5 shadow-2xl shadow-primary-900/10"><div className="mb-5 flex items-center justify-between"><div><p className="text-xs text-gray-500">Ringkasan bisnis</p><p className="mt-1 text-lg font-semibold text-gray-900">Performa distribusi</p></div><span className="rounded-lg bg-success-50 px-2 py-1 text-xs font-medium text-success-700">+12.8%</span></div><div className="grid grid-cols-3 gap-3">{[['Penjualan', 'Rp 128 jt'], ['Pesanan', '1.248'], ['Outlet aktif', '342']].map(([label, value]) => <div key={label} className="rounded-xl bg-gray-50 p-3"><p className="text-[11px] text-gray-500">{label}</p><p className="mt-1 text-sm font-bold text-gray-900">{value}</p></div>)}</div><div className="mt-5 flex h-32 items-end gap-2 rounded-xl bg-primary-50/60 p-4">{[32, 48, 40, 68, 54, 82, 72, 95, 78, 100, 88, 108].map((height, index) => <div key={index} className="flex-1 rounded-t bg-primary-500/80" style={{ height: `${height}%` }} />)}</div></div></div></div></section>
      <section className="mx-auto max-w-7xl px-5 py-16 sm:px-8"><div className="mb-10 text-center"><p className="text-sm font-semibold uppercase tracking-wider text-primary-600">Satu platform, banyak manfaat</p><h2 className="mt-2 text-2xl font-bold text-gray-900">Semua kebutuhan distribusi di satu tempat</h2></div><div className="grid gap-5 md:grid-cols-3">{features.map((feature) => <div key={feature.title} className="rounded-2xl border border-gray-200 bg-white p-6 shadow-card"><div className="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-primary-50 text-xl">{feature.icon}</div><h3 className="font-semibold text-gray-900">{feature.title}</h3><p className="mt-2 text-sm leading-6 text-gray-500">{feature.description}</p></div>)}</div></section>
      <footer className="border-t border-gray-100 px-5 py-6 text-center text-xs text-gray-400">Digital Distribution Management Platform · FMCG Digital Distribution Ecosystem</footer>
    </main>
  );
}
