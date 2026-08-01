import path from 'node:path';

import babel from '@rolldown/plugin-babel';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  // Compile with the React Compiler so tests exercise the same memoization
  // behavior as production builds (see vite.config.ts).
  plugins: [react(), babel({ plugins: ['babel-plugin-react-compiler'] })],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js'),
    },
  },
  test: {
    environment: 'happy-dom',
    setupFiles: ['./resources/js/test/setup.ts'],
  },
});
