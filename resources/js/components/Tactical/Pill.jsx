export function Pill({ variant = 'dusk', children, className = '' }) {
    const variants = {
        'nv-green': 'bg-nv-green/10 text-nv-green border-nv-green/30',
        amber: 'bg-amber/10 text-amber border-amber/30',
        'defcon-red': 'bg-defcon-red/10 text-defcon-red border-defcon-red/30',
        'comms-cyan': 'bg-comms-cyan/10 text-comms-cyan border-comms-cyan/30',
        dusk: 'bg-barrel/10 text-dusk border-graphite',
    };

    return (
        <span className={`inline-flex items-center px-2.5 py-1 text-xs font-mono rounded-md border ${variants[variant]} ${className}`}>
            {children}
        </span>
    );
}