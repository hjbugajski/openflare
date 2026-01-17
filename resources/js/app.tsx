import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import { Toast } from '@/components/ui/toast';
import type { ReverbConfig } from '@/types';

import '../css/app.css';

function initializeTheme() {
  const stored = localStorage.getItem('appearance');
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

  if (stored === 'dark' || (!stored && prefersDark)) {
    document.documentElement.classList.add('dark');
  } else {
    document.documentElement.classList.remove('dark');
  }
}

function initializeEcho(reverb: ReverbConfig) {
  configureEcho({
    broadcaster: 'reverb',
    key: reverb.key,
    wsHost: reverb.host,
    wsPort: reverb.port ?? 80,
    wssPort: reverb.port ?? 443,
    forceTLS: (reverb.scheme ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
  });
}

void createInertiaApp({
  title: (title) => (title ? `${title} - OpenFlare` : 'OpenFlare'),
  resolve: (name) => {
    return resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx'));
  },
  setup({ el, App, props }) {
    const reverb = props.initialPage.props.reverb as ReverbConfig | undefined;

    if (reverb?.key) {
      initializeEcho(reverb);
    }

    const root = createRoot(el);

    root.render(
      <StrictMode>
        <Toast.Provider>
          <App {...props} />
        </Toast.Provider>
      </StrictMode>,
    );
  },
  progress: {
    color: '#f97316',
  },
});

initializeTheme();
