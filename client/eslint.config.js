import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import { defineConfig, globalIgnores } from 'eslint/config'

export default defineConfig([
  globalIgnores(['dist']),
  {
    files: ['**/*.{js,jsx}'],
    extends: [
      js.configs.recommended,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
    languageOptions: {
      globals: globals.browser,
      parserOptions: { ecmaFeatures: { jsx: true } },
    },
    rules: {
      // O cadastro validado busca a entidade no efeito e preenche o formulário ali.
      'react-hooks/set-state-in-effect': 'off',
    },
  },
  {
    files: ['vite.config.js'],
    languageOptions: {
      globals: globals.node,
    },
  },
  {
    // Arquivos gerados pelo shadcn exportam variantes junto do componente
    // e importam React mesmo quando o JSX automático não usa o identificador.
    files: ['src/components/ui/**/*.{js,jsx}'],
    rules: {
      'react-refresh/only-export-components': 'off',
      'no-unused-vars': ['error', { varsIgnorePattern: '^React$' }],
    },
  },
])
