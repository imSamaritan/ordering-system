import type { ApiResponse } from '../types/index.js';

export function getBasePath(): string {
  const meta = document.querySelector<HTMLMetaElement>('meta[name="app-base-path"]');
  return meta?.content?.replace(/\/+$/, '') ?? '';
}

export function getCurrencySymbol(): string {
  const meta = document.querySelector<HTMLMetaElement>('meta[name="app-currency-symbol"]');
  return meta?.content ?? 'R';
}

export function formatCurrency(amount: number): string {
  return `${getCurrencySymbol()}${amount.toFixed(2)}`;
}

export async function apiRequest<T>(
  path: string,
  options: RequestInit = {}
): Promise<ApiResponse<T>> {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  const url = `${getBasePath()}/api/v1${normalizedPath}`;

  const headers = new Headers(options.headers);
  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json');
  }
  if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  const response = await fetch(url, {
    ...options,
    headers,
    credentials: 'same-origin',
  });

  const payload = (await response.json()) as ApiResponse<T>;
  if (!response.ok && payload.success !== false) {
    return {
      success: false,
      message: payload.message ?? `Request failed with status ${response.status}`,
      errors: payload.errors,
    };
  }

  return payload;
}
