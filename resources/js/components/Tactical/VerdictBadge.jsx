const VERDICT_CONFIG = {
    clear: { label: 'CLEAR', bg: 'bg-nv-green/10', text: 'text-nv-green', border: 'border-nv-green/30', iconColor: 'text-nv-green' },
    flagged: { label: 'FLAGGED', bg: 'bg-amber/10', text: 'text-amber', border: 'border-amber/30', iconColor: 'text-amber' },
    hostile: { label: 'HOSTILE', bg: 'bg-defcon-red/10', text: 'text-defcon-red', border: 'border-defcon-red/30', iconColor: 'text-defcon-red' },
};

const SIZE_CLASSES = {
    sm: { px: 'px-2', py: 'py-0.5', text: 'text-xs', gap: 'gap-1' },
    md: { px: 'px-3', py: 'py-1', text: 'text-sm', gap: 'gap-1.5' },
    lg: { px: 'px-4', py: 'py-1.5', text: 'text-base', gap: 'gap-2' },
};

export function VerdictBadge({ verdict, size = 'md', className = '' }) {
    const config = VERDICT_CONFIG[verdict];
    const { px, py, text, gap } = SIZE_CLASSES[size];

    return (
        <span className={`inline-flex items-center ${px} ${py} ${text} font-mono font-bold rounded-lg border ${config.bg} ${config.text} ${config.border} ${className}`}>
            <span className={`relative w-2 h-2 rounded-full ${config.iconColor}`} />
            {config.label}
        </span>
    );
}