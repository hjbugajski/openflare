import type { ComponentProps } from 'react';

export function IconArrowsSort(props: ComponentProps<'svg'>) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" {...props}>
      <path d="M3 8L7 4L11 8" stroke="currentColor" strokeWidth="2" strokeLinecap="square" />
      <path d="M13 16L17 20L21 16" stroke="currentColor" strokeWidth="2" strokeLinecap="square" />
      <path d="M7 5V20" stroke="currentColor" strokeWidth="2" strokeLinecap="square" />
      <path d="M17 4V19" stroke="currentColor" strokeWidth="2" strokeLinecap="square" />
    </svg>
  );
}
