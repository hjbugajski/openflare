import type { ComponentProps } from 'react';

export function IconArrowUp(props: ComponentProps<'svg'>) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" {...props}>
      <path d="M12 5V20" stroke="currentColor" strokeWidth="2" strokeLinecap="square" />
      <path d="M6 10L12 4L18 10" stroke="currentColor" strokeWidth="2" strokeLinecap="square" />
    </svg>
  );
}
