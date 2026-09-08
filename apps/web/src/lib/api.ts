export const API_URL = (process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api').replace(/\/$/, '');

export function apiUrl(path: string): string {
  return `${API_URL}/${path.replace(/^\//, '')}`;
}

export function authHeaders(token: string): HeadersInit {
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    Authorization: `Bearer ${token}`,
  };
}

export function getStoredToken(): string | null {
  if (typeof window === 'undefined') return null;
  return window.localStorage.getItem('ddp_token');
}

export function storeToken(token: string): void {
  window.localStorage.setItem('ddp_token', token);
}
