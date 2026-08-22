const getColor = (s) => {
    if (s >= 70) return 'var(--color-defcon-red, #ef4444)';
    if (s >= 35) return 'var(--color-amber, #f59e0b)';
    return 'var(--color-nv-green, #22c55e)';
};

export function ThreatMeter({
    score,
    size = 80,
    strokeWidth = 6,
    showLabel = true,
    className = '',
}) {
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference * (1 - Math.min(Math.max(score, 0), 100) / 100);

    const color = getColor(score);

    return (
        <div className={`inline-flex flex-col items-center ${className}`} style={{ width: size, height: size }}>
            <svg width={size} height={size} className="transform -rotate-90" data-testid="threat-meter-svg">
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="var(--color-graphite, #24303e)"
                    strokeWidth={strokeWidth}
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke={color}
                    strokeWidth={strokeWidth}
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    style={{ transition: 'stroke-dashoffset 500ms ease-out', filter: `drop-shadow(0 0 4px ${color})` }}
                />
            </svg>
            {showLabel && (
                <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <span className="text-xs font-mono font-bold text-bone" style={{ color }}>
                        {score}
                    </span>
                </div>
            )}
        </div>
    );
}