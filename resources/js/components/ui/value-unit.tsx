import type { ReactNode } from 'react';

import { cn } from '@/lib/cn';

interface ValueUnitProps {
  value: ReactNode;
  unit: string;
  suffix?: string;
  className?: string;
  unitClassName?: string;
  suffixClassName?: string;
}

export function ValueUnit({
  value,
  unit,
  suffix,
  className,
  unitClassName,
  suffixClassName,
}: ValueUnitProps) {
  return (
    <span className={cn('inline-flex items-baseline', className)}>
      <span>{value}</span>
      <span className={cn('ml-px text-muted-foreground', unitClassName)}>{unit}</span>
      {suffix ? <span className={cn('ml-1', suffixClassName)}>{suffix}</span> : null}
    </span>
  );
}
