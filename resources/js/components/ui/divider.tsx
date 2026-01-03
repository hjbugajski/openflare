import type { ComponentProps } from 'react';

export function Divider(props: ComponentProps<'div'>) {
  return <div className="my-4 border-t border-border" {...props} />;
}
