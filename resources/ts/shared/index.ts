import Alpine from 'alpinejs';
import { createAuthStore } from './authStore.js';
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
});
