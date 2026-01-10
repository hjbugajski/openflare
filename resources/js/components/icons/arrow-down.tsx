import type { ComponentProps } from 'react';

export function IconArrowDown(props: ComponentProps<'svg'>) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" {...props}>
      <path d="M18 14L12 20L6 14" stroke="currentColor" strokeWidth="2" strokeLinecap="square" />
      <path d="M12 19V4" stroke="currentColor" strokeWidth="2" strokeLinecap="square" />
    </svg>
  );
}
