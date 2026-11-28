/**
 * Formats a numeric currency amount with 2 decimal places and comma + non-breaking space
 * thousands grouping:
 *   50        => "R50.00"
 *   100       => "R100.00"
 *   1000      => "R1, 000.00"
 *   10000     => "R10, 000.00"
 *   120000    => "R120, 000.00"
 *   135599.99 => "R135, 599.99"
 *
 * Uses a non-breaking space after each comma so narrow dashboard cards never wrap mid-number.
 */
export function formatCurrencyAmount(
  amount: number | string | null | undefined,
  symbol = 'R'
): string {
  const numeric = typeof amount === 'number' ? amount : Number(amount ?? 0);
  const safe = Number.isFinite(numeric) ? numeric : 0;
  const isNegative = safe < 0;
  const [intPart = '0', decPart = '00'] = Math.abs(safe).toFixed(2).split('.');
  const groupedInt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',\u00A0');
  return `${isNegative ? '-' : ''}${symbol}${groupedInt}.${decPart}`;
}
