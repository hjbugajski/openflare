import type { ComponentProps } from 'react';

export function IconChevronLeft(props: ComponentProps<'svg'>) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" {...props}>
      <path d="M14 16L10 12L14 8" stroke="currentColor" strokeWidth="1.5" strokeLinecap="square" />
    </svg>
  );
}
