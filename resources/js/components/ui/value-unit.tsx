import type { ReactNode } from 'react';

import { cn } from '@/lib/cn';

interface ValueUnitProps {
  value: ReactNode;
  unit: string;
  suffix?: string;
  suffixValue?: ReactNode;
  suffixUnit?: string;
  className?: string;
  unitClassName?: string;
}

export function ValueUnit({
  value,
  unit,
  suffix,
  suffixValue,
  suffixUnit,
  className,
  unitClassName,
}: ValueUnitProps) {
  return (
    <span className={cn('inline-flex items-baseline', className)}>
      <span>{value}</span>
      <span className={cn('ml-px text-muted-foreground', unitClassName)}>{unit}</span>
      {suffixValue !== undefined ? (
        <>
          <span className="ml-1">{suffixValue}</span>
          <span className={cn('ml-px text-muted-foreground', unitClassName)}>{suffixUnit}</span>
        </>
      ) : null}
      {suffix ? <span className="ml-1">{suffix}</span> : null}
    </span>
  );
}
