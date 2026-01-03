import type { ComponentProps } from 'react';

export function IconMinus(props: ComponentProps<'svg'>) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" {...props}>
      <path d="M4 12H20" stroke="currentColor" strokeWidth="2" strokeLinecap="square" />
    </svg>
  );
}
