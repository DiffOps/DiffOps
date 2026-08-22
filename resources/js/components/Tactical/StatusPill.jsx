import { Loader2, CheckCircle, XCircle, Circle } from 'lucide-react';

const STATUS_CONFIG = {
    scanning: { icon: Loader2, label: 'SCANNING', class: 'text-comms-cyan animate-spin', bg: 'bg-comms-cyan/10', border: 'border-comms-cyan/30' },
    completed: { icon: CheckCircle, label: 'COMPLETED', class: 'text-nv-green', bg: 'bg-nv-green/10', border: 'border-nv-green/30' },
    failed: { icon: XCircle, label: 'FAILED', class: 'text-defcon-red', bg: 'bg-defcon-red/10', border: 'border-defcon-red/30' },
    idle: { icon: Circle, label: 'IDLE', class: 'text-dusk', bg: 'bg-barrel/10', border: 'border-graphite' },
};

export function StatusPill({ status, label, className = '' }) {
    const config = STATUS_CONFIG[status];
    const Icon = config.icon;

    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-mono rounded-md border ${config.bg} ${config.border} ${className}`}>
            <Icon className={`h-3 w-3 ${config.class}`} />
            <span>{label ?? config.label}</span>
        </span>
    );
}