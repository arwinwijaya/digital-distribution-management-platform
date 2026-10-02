import React from 'react';
import { render, waitFor } from '@testing-library/react';
import DashboardPage from './page';
import { loadDashboard } from '@/app/dashboard/api';
import { getStoredToken } from '@/lib/api';

const mockReplace = jest.fn();
const mockFetch = jest.fn();

jest.mock('next/navigation', () => ({
  useRouter: () => ({ replace: mockReplace }),
}));

jest.mock('@/app/dashboard/api', () => ({
  loadDashboard: jest.fn(),
}));

jest.mock('@/lib/api', () => ({
  apiUrl: (path: string) => path,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: jest.fn(),
}));

jest.mock('@/dummy/guards', () => ({
  useDummyRefresh: jest.fn(),
}));

jest.mock('@/components/LoginForm', () => ({
  __esModule: true,
  default: () => <div data-testid="login-form" />,
}));

jest.mock('@/components/Charts', () => ({
  OutletPerformanceChart: () => null,
  SalesTrendChart: () => null,
}));

jest.mock('@/components/ui', () => ({
  Button: ({ children }: { children: React.ReactNode }) => <button>{children}</button>,
  Card: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  Input: () => null,
  PageHeader: ({ title }: { title: string }) => <h1>{title}</h1>,
  Select: () => null,
  StatCard: () => null,
}));

const mockedGetStoredToken = getStoredToken as jest.MockedFunction<typeof getStoredToken>;
const mockedLoadDashboard = loadDashboard as jest.MockedFunction<typeof loadDashboard>;

describe('DashboardPage role routing', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    global.fetch = mockFetch;
    mockFetch.mockResolvedValue({
      ok: true,
      json: async () => ({ data: { role: 'outlet' } }),
    });
  });

  it('redirects unauthenticated users to the universal login page', async () => {
    mockedGetStoredToken.mockReturnValue(null);

    render(<DashboardPage />);

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/login?redirect=%2Fdashboard'));
    expect(mockFetch).not.toHaveBeenCalled();
    expect(mockedLoadDashboard).not.toHaveBeenCalled();
  });

  it('renders the outlet dashboard without redirecting to orders', async () => {
    mockedGetStoredToken.mockReturnValue('outlet-token');
    mockedLoadDashboard.mockResolvedValue({ kind: 'outlet', data: {
      summary: { total: 0, statuses: {}, recent: [], truncation: { capped: false, total: 0 } },
      shopping: { total: '0', count: 0, period_label: '30 hari terakhir' },
      credit: { credit_limit: null, outstanding_balance: '0', available_credit: null, hidden: true },
      favorites: [],
    } });

    const { findByTestId } = render(<DashboardPage />);

    expect(await findByTestId('outlet-dashboard')).toBeTruthy();
    expect(mockReplace).not.toHaveBeenCalledWith('/orders');
    expect(mockedLoadDashboard).toHaveBeenCalledWith('outlet-token', 'outlet', 'daily', undefined, undefined, 30, undefined);
  });
});
