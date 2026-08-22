import { TrendingUp, TrendingDown, Minus } from 'lucide-react';

export function HUDStat({ label, value, icon, trend = 'neutral', trendValue, className = '' }) {
    const trendIcons = { up: TrendingUp, down: TrendingDown, neutral: Minus };
    const trendColors = { up: 'text-nv-green', down: 'text-defcon-red', neutral: 'text-dusk' };
    const TrendIcon = trendIcons[trend];

    return (
        <div className={`p-4 ${className}`}>
            <div className="flex items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                    <p className="text-xs font-mono text-dusk uppercase tracking-wider mb-1">{label}</p>
                    <p className="text-3xl font-mono font-bold text-bone">{value}</p>
                    {trend !== 'neutral' && trendValue && (
                        <div className="flex items-center gap-1 mt-1">
                            <TrendIcon className={`h-3 w-3 ${trendColors[trend]}`} />
                            <span className={`text-xs font-mono ${trendColors[trend]}`}>{trendValue}</span>
                        </div>
                    )}
                </div>
                {icon && <div className="text-barrel flex-shrink-0">{icon}</div>}
            </div>
        </div>
    );
}