import { renderToString } from 'react-dom/server';

import { type ResolvedComponent, createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

createServer((page) =>
  createInertiaApp({
    page,
    render: renderToString,
    title: (title) => (title ? `${title} - OpenFlare` : 'OpenFlare'),
    resolve: (name) => {
      return resolvePageComponent(
        `./pages/${name}.tsx`,
        import.meta.glob<ResolvedComponent>('./pages/**/*.tsx', { import: 'default' }),
      );
    },
    setup: ({ App, props }) => {
      return <App {...props} />;
    },
  }),
);
