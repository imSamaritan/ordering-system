export interface StorefrontComponent {
  search: string;
  selectedCategory: string;
}

export function storefrontPage(): StorefrontComponent {
  return {
    search: '',
    selectedCategory: 'all',
  };
}

document.addEventListener('alpine:init', () => {
  window.Alpine.data('storefrontPage', storefrontPage);
});
