import type { ComponentProps } from 'react';

export function IconChevronDoubleLeft(props: ComponentProps<'svg'>) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" {...props}>
      <path
        d="M10 16L6 12L10 8M17 16L13 12L17 8"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="square"
      />
    </svg>
  );
}
