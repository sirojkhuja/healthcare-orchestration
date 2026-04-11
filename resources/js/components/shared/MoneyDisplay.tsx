import type { Money } from '@/types/common';

interface MoneyDisplayProps {
  money: Money;
  locale?: string;
  className?: string;
}

export function MoneyDisplay({ money, locale = 'en-US', className }: MoneyDisplayProps) {
  const formatted = new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: money.currency,
    minimumFractionDigits: 2,
  }).format(money.amount);

  return <span className={className}>{formatted}</span>;
}
