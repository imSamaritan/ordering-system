import Alpine from 'alpinejs';
import { createAuthStore, type AuthStore } from './authStore.js';
import { createCartStore } from './cartStore.js';
import { createToastStore } from './toast.js';

declare global {
  interface Window {
    Alpine: typeof Alpine;
  }
}

window.Alpine = Alpine;

Alpine.store('auth', createAuthStore());
Alpine.store('cart', createCartStore());
Alpine.store('toast', createToastStore());

document.addEventListener('DOMContentLoaded', () => {
  Alpine.start();
  const auth = Alpine.store('auth') as AuthStore;
  void auth.init();
});
