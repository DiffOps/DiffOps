export function Badge({ variant = 'neutral', children, className = '', ...props }) {
    const baseStyles = 'inline-flex items-center px-2 py-0.5 text-xs font-mono rounded-full border';
    const variants = {
        clear: 'bg-nv-green/10 text-nv-green border-nv-green/30',
        flagged: 'bg-amber/10 text-amber border-amber/30',
        hostile: 'bg-defcon-red/10 text-defcon-red border-defcon-red/30',
        neutral: 'bg-barrel/10 text-dusk border-graphite',
        scanning: 'bg-comms-cyan/10 text-comms-cyan border-comms-cyan/30 animate-pulse',
        completed: 'bg-nv-green/10 text-nv-green border-nv-green/30',
        failed: 'bg-defcon-red/10 text-defcon-red border-defcon-red/30',
    };

    return (
        <span className={`${baseStyles} ${variants[variant]} ${className}`} {...props}>
            {children}
        </span>
    );
}