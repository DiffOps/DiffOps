import { describe, it, expect } from 'vitest';

// Espelha o eager glob do app.jsx real: qualquer ReferenceError em nível de
// módulo (ex.: ícone lucide usado sem import) derruba TODO o bundle em produção.
const pages = import.meta.glob('../Pages/**/*.jsx', { eager: true });

describe('Pages modules', () => {
    it('imports every page without module-level crashes', () => {
        expect(Object.keys(pages).length).toBeGreaterThan(0);
    });
});
