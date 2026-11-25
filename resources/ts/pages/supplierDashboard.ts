export interface SupplierDashboardComponent {
  activeTab: 'companies' | 'inventory';
}

export function supplierDashboardPage(): SupplierDashboardComponent {
  return {
    activeTab: 'companies',
  };
}

document.addEventListener('alpine:init', () => {
  window.Alpine.data('supplierDashboardPage', supplierDashboardPage);
});
