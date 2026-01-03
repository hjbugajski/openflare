import type { ComponentProps } from 'react';

export function IconMenuVertical(props: ComponentProps<'svg'>) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" {...props}>
      <path d="M11 3H13V5H11V3Z" stroke="currentColor" strokeWidth="2" />
      <path d="M13 11H11V13H13V11Z" stroke="currentColor" strokeWidth="2" />
      <path d="M11 19H13V21H11V19Z" stroke="currentColor" strokeWidth="2" />
    </svg>
  );
}
