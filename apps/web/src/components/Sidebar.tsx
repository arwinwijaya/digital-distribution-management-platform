'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { usePathname } from 'next/navigation';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import { useDummyStore } from '@/dummy/store';
import { useRbacStore } from '@/store/useRbacStore';

type NavItem = { key: string; href: string; label: string; icon: string; adminHref?: string };
type SidebarAuth = { role: string | null; authResolved: boolean; authenticated: boolean };
type AuthChangeDetail = { token?: string | null; role?: string | null };

const NAV_ITEMS: NavItem[] = [
  // Operasional Harian
  { key: 'dashboard',    href: '/dashboard',     label: 'Dasbor',        icon: '📊' },
  { key: 'invoices',     href: '/invoices',      label: 'Invoice',       icon: '🧾' },
  { key: 'orders',       href: '/orders',        label: 'Pesanan',       icon: '🛒' },
  { key: 'products',     href: '/products',      label: 'Produk',        icon: '📦' },
  { key: 'outlets',      href: '/outlets',       label: 'Outlet',        icon: '🏪', adminHref: '/admin/outlets' },
  { key: 'marketplace',  href: '/marketplace',   label: 'Marketplace',   icon: '🌐' },
  { key: 'payments',     href: '/payments',      label: 'Pembayaran',    icon: '💳' },
  { key: 'delivery',     href: '/delivery',      label: 'Pengiriman',    icon: '🚚' },
  { key: 'sales',        href: '/sales',         label: 'Sales',         icon: '📋' },
  { key: 'worktree',     href: '/worktree',      label: 'Worktree',      icon: '🌳' },
  // Analitik & Insight (admin)
  { key: 'analytics',         href: '/analytics',         label: 'Analitik',         icon: '📈' },
  { key: 'data_intelligence', href: '/data-intelligence', label: 'Data Intelligence', icon: '🗺️' },
  { key: 'operations',        href: '/operations',        label: 'Operasi',          icon: '🔧' },
  // Admin Management
  { key: 'admin_orders',            href: '/admin/orders',            label: 'Approval Pesanan', icon: '⚙️' },
  { key: 'admin_products',          href: '/admin/products',          label: 'Harga Produk',     icon: '💰' },
  { key: 'admin_users',             href: '/admin/users',             label: 'Kelola pengguna',  icon: '👥' },
  { key: 'admin_promotions',        href: '/admin/promotions',        label: 'Kelola promosi',   icon: '🎁' },
  { key: 'admin_sales_performance', href: '/admin/sales-performance', label: 'Performa sales',   icon: '🎯' },
  { key: 'rbac_matrix',             href: '/admin/rbac',              label: 'Kelola Akses',     icon: '🔐' },
];

type MeResponse = { role: string | null; rbac: Record<string, string> | null };

async function fetchCurrentMe(token: string): Promise<MeResponse> {
  const response = await fetch(apiUrl('/auth/me'), { headers: authHeaders(token) });
  if (!response.ok) return { role: null, rbac: null };
  const body = await response.json();
  return { role: body?.data?.role ?? null, rbac: body?.data?.rbac ?? null };
}

function authChangeDetail(event: Event): { token: string | null; hintedRole?: string | null } {
  const detail = (event as CustomEvent<AuthChangeDetail>).detail;
  return {
    token: detail?.token === undefined ? getStoredToken() : detail.token,
    hintedRole: detail?.role,
  };
}

/**
 * Resolves the caller's role AND hydrates the RBAC store from `/auth/me`.
 *
 * The store is the single source of menu visibility; the server remains
 * authoritative (a hinted role renders immediately, then is reconciled).
 */
function createRoleSynchronizer(
  setRole: (role: string | null) => void,
  setAuthResolved: (resolved: boolean) => void,
  isActive: () => boolean,
) {
  let requestId = 0;
  return (token: string | null, hintedRole?: string | null) => {
    const currentRequest = ++requestId;
    if (!token) {
      setRole(null);
      useRbacStore.getState().reset();
      setAuthResolved(true);
      return;
    }

    // Show the login response immediately, then let the server remain authoritative.
    setRole(hintedRole ?? null);
    setAuthResolved(Boolean(hintedRole));
    fetchCurrentMe(token)
      .then(({ role, rbac }) => {
        if (isActive() && currentRequest === requestId) {
          setRole(role);
          useRbacStore.getState().hydrateFromMe(rbac, role);
          setAuthResolved(true);
        }
      })
      .catch(() => {
        if (isActive() && currentRequest === requestId) {
          setRole(null);
          useRbacStore.getState().reset();
          setAuthResolved(true);
        }
      });
  };
}

function useSidebarAuth(): SidebarAuth {
  const [role, setRole] = useState<string | null>(null);
  const [authResolved, setAuthResolved] = useState(false);
  const [authenticated, setAuthenticated] = useState(false);

  useEffect(() => {
    let active = true;

    // Dummy ON resolves the role offline (from localStorage); dummy OFF asks /auth/me.
    const isDummy = useDummyStore.getState().isDummy;
    const syncRole = createRoleSynchronizer(setRole, setAuthResolved, () => active);

    const handleToken = (token: string | null, hintedRole?: string | null) => {
      if (!active) return;
      setAuthenticated(Boolean(token));
      if (isDummy) {
        const dummyRole = hintedRole ?? localStorage.getItem('ddp_role');
        setRole(dummyRole);
        // Dummy hydration is pure — `hydrateFromMe` reads the dummy matrix.
        useRbacStore.getState().hydrateFromMe(null, dummyRole);
        setAuthResolved(true);
        return;
      }
      syncRole(token, hintedRole);
    };

    const handleAuthChange = (event: Event) => {
      const { token, hintedRole } = authChangeDetail(event);
      handleToken(token, hintedRole);
    };
    const handleStorageChange = (event: StorageEvent) => {
      if (event.key === 'ddp_token') handleToken(event.newValue);
    };

    handleToken(getStoredToken());
    window.addEventListener('ddp-auth-change', handleAuthChange);
    window.addEventListener('storage', handleStorageChange);
    return () => {
      active = false;
      window.removeEventListener('ddp-auth-change', handleAuthChange);
      window.removeEventListener('storage', handleStorageChange);
    };
  }, []);

  // Note: logout in Topbar does a full page reload, so no in-tab event is needed
  // to clear the menus — remounting with no token empties them.
  return { role, authResolved, authenticated };
}

function SidebarNavigation({ pathname, role, authResolved, authenticated, onClose }: { pathname: string; role: string | null; authResolved: boolean; authenticated: boolean; onClose?: () => void }) {
  // Subscribe to the store so visibility re-renders once hydration lands.
  const map = useRbacStore((state) => state.map);
  const visibleItems = !authResolved || !authenticated
    ? []
    : NAV_ITEMS.filter((item) => (map?.[item.key] ?? 'none') !== 'none');
  return <nav className="flex-1 overflow-y-auto slim-scroll px-3 py-4 space-y-1">
    {visibleItems.map((item) => {
      const { label, icon } = item;
      // Admins manage outlets on the admin page; other roles keep the public
      // registration form. Other items simply use their declared href.
      const href = item.adminHref && role === 'admin' ? item.adminHref : item.href;
      const isActive = pathname === href || pathname.startsWith(href + '/');
      return <Link key={href} href={href} onClick={onClose} className={`flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13.5px] font-medium transition-colors duration-100 ${isActive ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'}`}>
        <span className="text-base" aria-hidden>{icon}</span>
        <span>{label}</span>
      </Link>;
    })}
  </nav>;
}

function SidebarBrand() {
  return <div className="flex h-16 shrink-0 items-center gap-2.5 border-b border-gray-100 px-5">
    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-600 text-sm font-bold text-white">D</div>
    <span className="text-[15px] font-semibold text-gray-900 truncate">Digital Dist.</span>
  </div>;
}

function SidebarFooter() {
  return <div className="shrink-0 border-t border-gray-100 px-5 py-4">
    <p className="text-[11px] text-gray-400 leading-tight">Digital Distribution<br />Management Platform</p>
  </div>;
}

export default function Sidebar({ onClose }: { onClose?: () => void }) {
  const pathname = usePathname();
  const { role, authResolved, authenticated } = useSidebarAuth();

  return <aside className="fixed inset-y-0 left-0 z-40 flex w-60 flex-col bg-white border-r border-gray-200 shadow-sidebar">
    <SidebarBrand />
    <SidebarNavigation pathname={pathname} role={role} authResolved={authResolved} authenticated={authenticated} onClose={onClose} />
    <SidebarFooter />
  </aside>;
}
