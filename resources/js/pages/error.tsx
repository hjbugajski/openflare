import { Head, Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Heading } from '@/components/ui/heading';
import { home } from '@/routes';

interface Props {
  status: number;
}

interface ErrorCopy {
  title: string;
  message: string;
}

const ERROR_COPY: Record<number, ErrorCopy> = {
  403: {
    title: 'forbidden',
    message: 'this resource is not yours to read.',
  },
  404: {
    title: 'page not found',
    message: 'no route responded at this address.',
  },
  500: {
    title: 'server error',
    message: 'something failed on our end. the incident has been logged.',
  },
  503: {
    title: 'service unavailable',
    message: 'openflare is offline for maintenance. check back shortly.',
  },
};

const FALLBACK_COPY: ErrorCopy = {
  title: 'unexpected error',
  message: 'the request could not be completed.',
};

export default function ErrorPage({ status }: Props) {
  const copy = ERROR_COPY[status] ?? FALLBACK_COPY;

  return (
    <div className="mx-auto flex h-full w-full max-w-xl flex-col items-center justify-center gap-6 px-4">
      <Head title={copy.title} />

      <p className="text-sm text-accent">
        <span aria-hidden>[</span>
        <span className="mx-1">openflare</span>
        <span aria-hidden>]</span>
      </p>

      {/* The status code scanned through by the same raster as the CRT theme. */}
      <div
        aria-hidden
        className="bg-[repeating-linear-gradient(to_bottom,var(--color-foreground)_0px,var(--color-foreground)_4px,transparent_4px,transparent_7px)] bg-clip-text text-[9rem] leading-none font-semibold tracking-tighter text-transparent tabular-nums select-none"
      >
        {status}
      </div>

      <Heading title={copy.title} description={copy.message} className="items-center text-center" />

      <Button render={<Link href={home().url} />}>back to dashboard</Button>
    </div>
  );
}
