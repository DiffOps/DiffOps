const DEFCON_COLORS = {
    1: 'var(--color-defcon-red, #ef4444)',
    2: 'var(--color-defcon-red, #ef4444)',
    3: 'var(--color-amber, #f59e0b)',
    4: 'var(--color-comms-cyan, #38bdf8)',
    5: 'var(--color-nv-green, #22c55e)',
};

const DEFCON_LABELS = {
    1: 'CRITICAL',
    2: 'HIGH',
    3: 'ELEVATED',
    4: 'GUARDED',
    5: 'LOW',
};

const SIZE_CLASSES = {
    sm: { container: 'w-20 h-6', segment: 'h-full', font: 'text-[9px]' },
    md: { container: 'w-32 h-8', segment: 'h-full', font: 'text-xs' },
    lg: { container: 'w-48 h-12', segment: 'h-full', font: 'text-sm' },
};

export function DefconMeter({ level, size = 'md', className = '' }) {
    const colors = DEFCON_COLORS[level];
    const label = DEFCON_LABELS[level];
    const { container, segment, font } = SIZE_CLASSES[size];

    return (
        <div className={`relative ${container} ${className}`}>
            <div className="flex h-full rounded-md overflow-hidden bg-graphite">
                {[1, 2, 3, 4, 5].map((l) => (
                    <div
                        key={l}
                        className={`flex-1 transition-all duration-300 ${l <= level ? 'bg-current' : 'bg-graphite/50'}`}
                        style={{ backgroundColor: l <= level ? colors : undefined }}
                    />
                ))}
            </div>
            <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                <span className={`font-mono font-bold ${font} text-obsidian`}>{label}</span>
            </div>
            <div className="absolute bottom-full left-0 right-0 mb-1 flex justify-between text-[8px] font-mono text-dusk">
                {[5, 4, 3, 2, 1].map((l) => (
                    <span key={l} className={l <= level ? 'font-bold text-bone' : ''}>
                        {l}
                    </span>
                ))}
            </div>
        </div>
    );
}