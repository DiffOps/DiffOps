import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    title: (title) => (title ? `${title} — DiffOps` : 'DiffOps'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        return pages[`./Pages/${name}.jsx`];
    },
    setup({ App, props }) {
        return renderToString(<App {...props} />);
    },
    progress: {
        color: '#00e5ff',
    },
});