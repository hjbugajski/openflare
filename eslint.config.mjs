import js from '@eslint/js';
import { defineConfig, globalIgnores } from 'eslint/config';
import prettierConfig from 'eslint-config-prettier/flat';
import importPlugin from 'eslint-plugin-import';
import reactPlugin from 'eslint-plugin-react';
import reactHooksPlugin from 'eslint-plugin-react-hooks';
import globals from 'globals';
import typescript from 'typescript-eslint';

export default defineConfig([
  globalIgnores([
    '**/node_modules/**',
    '**/dist/**',
    'resources/js/actions/**',
    'resources/js/routes/**',
  ]),
  {
    files: ['**/*.{js,mjs,jsx,ts,mts,tsx}'],
    plugins: { js },
    extends: ['js/recommended', importPlugin.flatConfigs.recommended],
    settings: {
      'import/resolver': {
        typescript: {
          alwaysTryTypes: true,
          project: 'tsconfig.json',
        },
      },
      react: { version: 'detect' },
    },
    languageOptions: { globals: globals.browser },
    rules: {
      'sort-imports': [
        'error',
        {
          ignoreCase: false,
          ignoreDeclarationSort: true,
          ignoreMemberSort: false,
          memberSyntaxSortOrder: ['none', 'all', 'multiple', 'single'],
          allowSeparatedGroups: true,
        },
      ],
      'import/order': [
        'error',
        {
          alphabetize: { order: 'asc', caseInsensitive: true },
          groups: ['builtin', 'external', 'internal', ['parent', 'sibling'], 'index', 'unknown'],
          'newlines-between': 'always',
          pathGroups: [
            {
              pattern: 'react',
              group: 'external',
              position: 'before',
            },
            {
              pattern: '@/**',
              group: 'index',
              position: 'before',
            },
            {
              pattern: '{.,..}/**/*.css',
              group: 'unknown',
              position: 'after',
            },
          ],
          pathGroupsExcludedImportTypes: ['builtin', 'react', 'internal', 'index'],
          warnOnUnassignedImports: true,
        },
      ],
    },
  },
  {
    files: ['**/*.{ts,mts,tsx}'],
    extends: [typescript.configs.recommended, importPlugin.flatConfigs.typescript],
    rules: {
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/consistent-type-imports': 'error',
    },
  },
  {
    files: ['**/*.{jsx,tsx}'],
    extends: [reactPlugin.configs.flat.recommended, reactHooksPlugin.configs.flat.recommended],
    rules: {
      'react/react-in-jsx-scope': 'off',
      'react/jsx-sort-props': [
        'warn',
        {
          callbacksLast: true,
          ignoreCase: true,
          noSortAlphabetically: true,
          reservedFirst: true,
          shorthandFirst: true,
        },
      ],
    },
  },
  prettierConfig,
]);
