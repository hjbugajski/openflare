import type { ReactNode } from 'react';
import { createElement } from 'react';

const formatter = new Intl.DateTimeFormat(undefined, {
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
});

const MUTED_TYPES = new Set<Intl.DateTimeFormatPartTypes>(['literal', 'dayPeriod']);

export function formatDateTime(date: string): ReactNode {
  const parts = formatter.formatToParts(new Date(date));

  return createElement(
    'span',
    null,
    parts.map((part, i) =>
      MUTED_TYPES.has(part.type)
        ? createElement('span', { key: i, className: 'text-muted-foreground' }, part.value)
        : createElement('span', { key: i }, part.value),
    ),
  );
}
