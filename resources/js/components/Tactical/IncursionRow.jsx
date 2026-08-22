import { VerdictBadge, ThreatMeter, DefconMeter, StatusPill } from './index';
import { GitBranch, Clock, User } from 'lucide-react';

export function IncursionRow({
    id,
    timestamp,
    repository,
    prNumber,
    author,
    verdict,
    threatScore,
    defconLevel,
    executionTimeMs,
    status,
    onClick,
    className = '',
}) {
    const formattedTime = new Date(timestamp).toLocaleString('en-US', {
        hour12: false,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).replace(',', '');

    return (
        <button
            onClick={onClick}
            className={`w-full flex items-center gap-4 p-3 bg-plate/50 hover:bg-plate border-b border-graphite/50 transition-colors text-left ${onClick ? 'cursor-pointer' : ''} ${className}`}
        >
            <div className="flex items-center gap-2 text-dusk font-mono text-xs w-40 flex-shrink-0">
                <GitBranch className="h-3 w-3" />
                <span className="truncate">{repository}#{prNumber}</span>
            </div>
            <div className="flex items-center gap-2 text-bone text-sm w-36 flex-shrink-0">
                {author.avatarUrl && <img src={author.avatarUrl} alt="" className="h-5 w-5 rounded-full" />}
                <User className="h-4 w-4 text-barrel" />
                <span className="font-mono truncate">{author.username}</span>
            </div>
            <VerdictBadge verdict={verdict} size="sm" />
            <div className="flex items-center gap-2 w-24">
                <ThreatMeter score={threatScore} size={48} strokeWidth={3} showLabel />
            </div>
            <DefconMeter level={defconLevel} size="sm" />
            <div className="flex items-center gap-1.5 text-dusk font-mono text-xs w-28">
                <Clock className="h-3 w-3" />
                <span>{executionTimeMs}ms</span>
            </div>
            <StatusPill status={status} />
            <div className="flex-1 text-right text-dusk font-mono text-xs w-48">{formattedTime}</div>
        </button>
    );
}