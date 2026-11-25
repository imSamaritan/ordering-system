export interface CustomerDashboardComponent {
  statusFilter: string;
  orderNumberFilter: string;
}

export function customerDashboardPage(): CustomerDashboardComponent {
  return {
    statusFilter: 'all',
    orderNumberFilter: '',
  };
}

document.addEventListener('alpine:init', () => {
  window.Alpine.data('customerDashboardPage', customerDashboardPage);
});
