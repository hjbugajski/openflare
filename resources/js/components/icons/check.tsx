import type { ComponentProps } from 'react';

export function IconCheck(props: ComponentProps<'svg'>) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" {...props}>
      <path d="M7 13L10 16L17 8" stroke="currentColor" strokeWidth="2" strokeLinecap="square" />
    </svg>
  );
}
