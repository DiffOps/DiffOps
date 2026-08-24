import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

// Contrato do Design System TACTICAL OPS (DiffOps.md §7.2/7.3): sem essas
// variáveis no @theme do Tailwind v4, utilitários como bg-obsidian não
// geram CSS e as telas nascem com fundo/texto padrão do navegador.
const css = readFileSync(resolve(process.cwd(), 'resources/css/app.css'), 'utf8');

const REQUIRED_COLORS = {
    '--color-obsidian': '#0a0c10',
    '--color-asphalt': '#0f1318',
    '--color-plate': '#141a21',
    '--color-steel': '#1a222b',
    '--color-graphite': '#24303e',
    '--color-barrel': '#334155',
    '--color-bone': '#e2e8f0',
    '--color-dusk': '#94a3b8',
    '--color-nv-green': '#22c55e',
    '--color-amber': '#f59e0b',
    '--color-defcon-red': '#ef4444',
    '--color-comms-cyan': '#38bdf8',
};

describe('TACTICAL OPS theme tokens', () => {
    it('declares every palette color in the css theme', () => {
        for (const [token, hex] of Object.entries(REQUIRED_COLORS)) {
            expect(css).toContain(`${token}: ${hex}`);
        }
    });

    it('uses the design system typography', () => {
        expect(css).toContain("--font-mono: 'JetBrains Mono'");
        expect(css).toContain("--font-sans: 'Inter'");
    });
});
