import Alpine from 'alpinejs';
import { createAuthStore, type AuthStore } from './authStore.js';
import { createCartStore, type CartStore } from './cartStore.js';
import { createThemeStore, type ThemeStore } from './themeStore.js';
import { createToastStore } from './toast.js';

declare global {
  interface Window {
    Alpine: typeof Alpine;
  }
}

window.Alpine = Alpine;

Alpine.store('theme', createThemeStore());
Alpine.store('auth', createAuthStore());
Alpine.store('cart', createCartStore());
Alpine.store('toast', createToastStore());

document.addEventListener('DOMContentLoaded', () => {
  const theme = Alpine.store('theme') as ThemeStore;
  theme.init();
  Alpine.start();
  const cart = Alpine.store('cart') as CartStore;
  cart.init();
  const auth = Alpine.store('auth') as AuthStore;
  void auth.init();
});
