export function Card({ children, className = '', variant = 'default', corners = true }) {
    const variants = {
        default: 'bg-plate border-graphite',
        elevated: 'bg-steel border-graphite shadow-lg',
        outlined: 'bg-transparent border-graphite',
    };
    const cornerStyles = corners
        ? 'relative before:absolute before:top-0 before:left-0 before:w-3 before:h-3 before:border-t-graphite before:border-l-graphite after:absolute after:top-0 after:right-0 after:w-3 after:h-3 after:border-t-graphite after:border-r-graphite before:absolute before:bottom-0 before:left-0 before:w-3 before:h-3 before:border-b-graphite before:border-l-graphite after:absolute after:bottom-0 after:right-0 after:w-3 after:h-3 after:border-b-graphite after:border-r-graphite'
        : '';

    return (
        <div className={`rounded-lg border ${variants[variant]} ${cornerStyles} ${className}`}>
            <div className="p-4">{children}</div>
        </div>
    );
}