import '@testing-library/jest-dom';
import { render, screen, waitFor, within } from '@testing-library/react';
import LoginPage from './page';
import { apiUrl } from '@/lib/api';

const mockReplace = jest.fn();
const mockRefresh = jest.fn();
let mockRedirectParam: string | null = null;

jest.mock('next/navigation', () => ({
  useRouter: () => ({ replace: mockReplace, refresh: mockRefresh }),
  useSearchParams: () => ({
    get: (key: string) => (key === 'redirect' ? mockRedirectParam : null),
  }),
}));

const originalFetch = global.fetch;

type FetchResponse = {
  ok: boolean;
  status: number;
  json: () => Promise<unknown>;
};

function response(ok: boolean, body: unknown, status = ok ? 200 : 401): FetchResponse {
  return { ok, status, json: async () => body };
}

function mockFetchByEndpoint({
  me = response(true, { data: { role: 'outlet' } }),
  login = response(true, { data: { token: 'token-outlet', user: { role: 'outlet' } } }),
}: {
  me?: FetchResponse | Promise<FetchResponse>;
  login?: FetchResponse | Promise<FetchResponse>;
} = {}) {
  global.fetch = jest.fn(async (input: RequestInfo | URL) => {
    const url = String(input);
    if (url === apiUrl('/auth/me')) return me;
    if (url === apiUrl('/auth/login')) return login;
    throw new Error(`Unexpected fetch URL: ${url}`);
  }) as jest.Mock;
  return global.fetch as jest.Mock;
}

async function renderLoginPage() {
  render(<LoginPage />);
  await waitFor(() => expect(screen.getByRole('button', { name: 'Masuk & pesan ulang' })).toBeInTheDocument());
}

describe('LoginPage outlet-first presentation', () => {
  beforeEach(() => {
    localStorage.clear();
    mockRedirectParam = null;
    mockReplace.mockClear();
    mockRefresh.mockClear();
    mockFetchByEndpoint();
  });

  afterEach(() => {
    global.fetch = originalFetch;
    jest.restoreAllMocks();
  });

  it('renders the outlet story, exactly three benefits, reorder CTA, and responsive hero/form sibling contract', async () => {
    await renderLoginPage();

    expect(screen.getByText(/pesanan ulang untuk outlet/i)).toBeInTheDocument();
    expect(screen.getByText(/lihat kebutuhan outlet/i)).toBeInTheDocument();

    const benefits = within(screen.getByTestId('login-benefits')).getAllByRole('listitem');
    expect(benefits).toHaveLength(3);
    expect(benefits.map((item) => item.textContent)).toEqual([
      expect.stringMatching(/ulang pesanan/i),
      expect.stringMatching(/pantau status/i),
      expect.stringMatching(/akses katalog/i),
    ]);

    expect(screen.getByRole('button', { name: 'Masuk & pesan ulang' })).toBeInTheDocument();

    const layout = screen.getByTestId('login-layout');
    expect(layout).toHaveClass('grid');
    expect(layout).toHaveClass('grid-cols-1');
    expect(layout).toHaveClass('lg:grid-cols-2');

    const formPanel = screen.getByTestId('login-form-panel');
    const heroPanel = screen.getByTestId('login-hero-panel');
    expect(formPanel.parentElement).toBe(layout);
    expect(heroPanel.parentElement).toBe(layout);
  });

  it('uses honest ability-framed copy and makes no personal stock/promo claims', async () => {
    await renderLoginPage();

    expect(screen.getByText(/setelah masuk anda bisa/i)).toBeInTheDocument();

    const bodyText = document.body.textContent?.toLowerCase() ?? '';
    expect(bodyText).not.toMatch(/stok anda/);
    expect(bodyText).not.toMatch(/promo anda/);
    expect(bodyText).not.toMatch(/rekomendasi/);
    expect(bodyText).not.toMatch(/inventaris anda/);
    expect(bodyText).not.toMatch(/inventory/);
  });

  it('orders branding/form/submit before benefits with no order-* visual reversal on mobile', async () => {
    await renderLoginPage();

    const heading = screen.getByRole('heading', { name: /^masuk$/i });
    const submit = screen.getByRole('button', { name: 'Masuk & pesan ulang' });
    const benefits = screen.getByTestId('login-benefits');
    const firstBenefit = within(benefits).getAllByRole('listitem')[0];

    expect(heading.compareDocumentPosition(submit) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(submit.compareDocumentPosition(firstBenefit) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(heading.compareDocumentPosition(firstBenefit) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();

    const layout = screen.getByTestId('login-layout');
    const scoped = [layout, ...Array.from(layout.querySelectorAll('*'))];
    for (const element of scoped) {
      for (const cls of Array.from(element.classList)) {
        expect(cls).not.toMatch(/^order-/);
      }
    }
  });

  it('shows demo credentials only as subtle footer help after submit button', async () => {
    await renderLoginPage();

    const submit = screen.getByRole('button', { name: 'Masuk & pesan ulang' });
    const demoText = screen.getByText(/akun demo outlet/i);

    expect(submit.compareDocumentPosition(demoText) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(demoText.closest('[data-testid="login-form-panel"]')).not.toBeNull();
    expect(demoText).toHaveAttribute('data-testid', 'login-demo-footer');
    expect(demoText).toHaveClass('text-xs');
    expect(demoText).toHaveClass('text-gray-500');
  });

  it('bypasses the login form when a valid stored token and role exist', async () => {
    localStorage.setItem('ddp_token', 'valid-token');
    localStorage.setItem('ddp_role', 'outlet');
    const fetchMock = mockFetchByEndpoint({
      me: response(true, { data: { role: 'outlet' } }),
    });

    await import('./page');
    render(<LoginPage />);

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/orders'));
    expect(fetchMock).toHaveBeenCalledWith(apiUrl('/auth/me'), expect.objectContaining({ headers: expect.any(Object) }));
    expect(screen.queryByRole('button', { name: 'Masuk & pesan ulang' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Masuk' })).not.toBeInTheDocument();
  });

  it('shows a usable login form when stored token is stale or /auth/me fails', async () => {
    localStorage.setItem('ddp_token', 'stale-token');
    localStorage.setItem('ddp_role', 'outlet');
    mockFetchByEndpoint({
      me: response(false, {}, 401),
    });

    render(<LoginPage />);

    await waitFor(() => expect(screen.getByRole('button', { name: 'Masuk & pesan ulang' })).toBeInTheDocument());
    expect(mockReplace).not.toHaveBeenCalled();
    expect(screen.queryByText(/memuat/i)).not.toBeInTheDocument();
  });

  it('also recovers from a rejected /auth/me without redirect', async () => {
    localStorage.setItem('ddp_token', 'stale-token');
    const fetchMock = mockFetchByEndpoint({
      me: Promise.reject(new Error('Network failure')) as unknown as FetchResponse,
    });

    render(<LoginPage />);

    await waitFor(() => expect(screen.getByRole('button', { name: 'Masuk & pesan ulang' })).toBeInTheDocument());
    expect(mockReplace).not.toHaveBeenCalled();
    expect(screen.queryByText(/memuat/i)).not.toBeInTheDocument();
    expect(fetchMock).toHaveBeenCalledWith(apiUrl('/auth/me'), expect.objectContaining({ headers: expect.any(Object) }));
  });
});
