export default [
    {
        files: ['**/*.{js,jsx}'],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                browser: true,
                es2022: true,
            },
            parserOptions: {
                ecmaFeatures: {
                    jsx: true,
                },
            },
        },
        settings: {
            react: {
                version: '18.3',
            },
        },
        plugins: {
            react: await import('eslint-plugin-react'),
            'react-hooks': await import('eslint-plugin-react-hooks'),
            'react-refresh': await import('eslint-plugin-react-refresh'),
        },
        rules: {
            'react/jsx-no-target-blank': 'off',
            'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
            'react-hooks/rules-of-hooks': 'error',
            'react-hooks/exhaustive-deps': 'warn',
        },
    },
];