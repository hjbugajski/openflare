import type { ComponentProps } from 'react';

export function IconTable(props: ComponentProps<'svg'>) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" {...props}>
      <path
        d="M20 4H21V3H20V4ZM20 20V21H21V20H20ZM4 20H3V21H4V20ZM4 4V3H3V4H4ZM11 4V3H9V4H11ZM9 20V21H11V20H9ZM4 9H3V11H4V9ZM20 11H21V9H20V11ZM19 4V20H21V4H19ZM20 19H4V21H20V19ZM5 20V4H3V20H5ZM4 5H20V3H4V5ZM9 4V10H11V4H9ZM9 10V20H11V10H9ZM4 11H10V9H4V11ZM10 11H20V9H10V11Z"
        fill="currentColor"
      />
    </svg>
  );
}
