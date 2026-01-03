import type { PropsWithChildren } from 'react';

export default function AuthLayout({ children }: PropsWithChildren) {
  return (
    <div className="mx-auto flex h-full w-full max-w-md flex-col items-center justify-center gap-4 px-4">
      <h1 className="text-lg text-accent">
        <span aria-hidden>[</span>
        <span className="mx-1">openflare</span>
        <span aria-hidden>]</span>
      </h1>
      {children}
    </div>
  );
}
