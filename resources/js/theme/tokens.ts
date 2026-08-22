export const tokens = {
    colors: {
        obsidian: '#0a0c10',
        asphalt: '#0f1318',
        plate: '#141a21',
        steel: '#1a222b',
        graphite: '#24303e',
        barrel: '#334155',
        bone: '#e2e8f0',
        dusk: '#94a3b8',
        'nv-green': '#22c55e',
        amber: '#f59e0b',
        'defcon-red': '#ef4444',
        'comms-cyan': '#38bdf8',
    },
    fontMono: "'JetBrains Mono', ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace",
    fontSans: "'Inter', ui-sans-serif, system-ui, sans-serif",
    spacing: {
        0: '0',
        1: '0.25rem',
        2: '0.5rem',
        3: '0.75rem',
        4: '1rem',
        5: '1.25rem',
        6: '1.5rem',
        8: '2rem',
        10: '2.5rem',
        12: '3rem',
        16: '4rem',
    },
    radius: {
        none: '0',
        sm: '0.25rem',
        md: '0.375rem',
        lg: '0.5rem',
        xl: '0.75rem',
        '2xl': '1rem',
        full: '9999px',
    },
    shadows: {
        sm: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
        md: '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
        lg: '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
        xl: '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
        'inner': 'inset 0 2px 4px 0 rgb(0 0 0 / 0.05)',
    },
    breakpoints: {
        sm: '640px',
        md: '768px',
        lg: '1024px',
        xl: '1280px',
        '2xl': '1536px',
    },
    zIndex: {
        hide: -1,
        base: 0,
        dropdown: 1000,
        sticky: 1100,
        modal: 1300,
        popover: 1400,
        tooltip: 1500,
    },
    transition: {
        fast: '150ms ease',
        normal: '200ms ease',
        slow: '300ms ease',
    },
} as const;

export type TokenColors = keyof typeof tokens.colors;
export type TokenSpacing = keyof typeof tokens.spacing;
export type TokenRadius = keyof typeof tokens.radius;
export type TokenShadow = keyof typeof tokens.shadows;
export type TokenBreakpoint = keyof typeof tokens.breakpoints;
export type TokenZIndex = keyof typeof tokens.zIndex;

export function colorToken(name: TokenColors): string {
    return tokens.colors[name];
}

export function spacingToken(name: TokenSpacing): string {
    return tokens.spacing[name];
}

export function radiusToken(name: TokenRadius): string {
    return tokens.radius[name];
}

export function shadowToken(name: TokenShadow): string {
    return tokens.shadows[name];
}