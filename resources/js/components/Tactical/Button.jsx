import { Loader2 } from 'lucide-react';

export function Button({
    variant = 'metallic',
    size = 'md',
    loading = false,
    leftIcon,
    rightIcon,
    disabled,
    className = '',
    children,
    ...props
}) {
    const baseStyles = 'inline-flex items-center justify-center font-mono font-medium rounded-lg border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-comms-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-obsidian disabled:opacity-50 disabled:cursor-not-allowed';
    const variants = {
        primary: 'bg-nv-green text-obsidian border-nv-green hover:bg-nv-green/90',
        danger: 'bg-defcon-red text-bone border-defcon-red hover:bg-defcon-red/90',
        ghost: 'bg-transparent text-bone border-graphite hover:bg-plate',
        metallic: 'bg-plate text-bone border-graphite hover:bg-steel hover:border-barrel',
    };
    const sizes = {
        sm: 'px-3 py-1.5 text-xs gap-1.5',
        md: 'px-4 py-2 text-sm gap-2',
        lg: 'px-6 py-3 text-base gap-2.5',
    };

    return (
        <button
            className={`${baseStyles} ${variants[variant]} ${sizes[size]} ${className}`}
            disabled={disabled || loading}
            {...props}
        >
            {loading ? (
                <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
                <>
                    {leftIcon && <span className="flex-shrink-0">{leftIcon}</span>}
                    {children}
                    {rightIcon && <span className="flex-shrink-0">{rightIcon}</span>}
                </>
            )}
        </button>
    );
}