import type { Metadata } from 'next';
import { Inter } from 'next/font/google';
import './globals.css';
import AppShell from '@/components/AppShell';
import DummyBootstrap from '@/components/DummyBootstrap';
import PwaBootstrap from '@/components/PwaBootstrap';

const inter = Inter({ subsets: ['latin'] });

export const metadata: Metadata = {
  title: 'Digital Distribution Management Platform',
  description: 'FMCG Digital Distribution Ecosystem',
  appleWebApp: { capable: true, title: 'DDP', statusBarStyle: 'default' },
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="id">
      <body className={inter.className}>
        <DummyBootstrap />
        <PwaBootstrap />
        <AppShell>{children}</AppShell>
      </body>
    </html>
  );
}
