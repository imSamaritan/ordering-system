import type { User } from '../types/index.js';
import { apiRequest } from './api.js';
import type { CartStore } from './cartStore.js';

export interface AuthStore {
  user: User | null;
  loading: boolean;
  init(): Promise<void>;
  logout(): Promise<void>;
}

export function createAuthStore(): AuthStore {
  return {
    user: null,
    loading: false,

    async init(): Promise<void> {
      this.loading = true;
      try {
        const res = await apiRequest<{ user: User }>('/auth/me');
        this.user = res.success && res.data ? res.data.user : null;
        if (this.user?.role === 'supplier') {
          const cart = window.Alpine?.store('cart') as CartStore | undefined;
          cart?.close();
        }
      } catch {
        this.user = null;
      } finally {
        this.loading = false;
      }
    },

    async logout(): Promise<void> {
      await apiRequest('/auth/logout', { method: 'POST' });
      this.user = null;
    },
  };
}
