import { useEffect, useState } from 'react';
import { usePage, Head } from '@inertiajs/react';
import { LayoutDashboard, GitBranch, Activity, Shield, TrendingUp, TrendingDown, Clock, Zap, Radio, Users } from 'lucide-react';
import { HUDStat, IncursionRow, StatusPill, EmptyOrgState } from '@/components/Tactical';
import { useRealtime } from '@/hooks/useRealtime';

export default function Dashboard() {
    const { stats, incursions, realtime, currentOrganization = null } = usePage().props;
    const [feed, setFeed] = useState(incursions);

    if (currentOrganization === null) {
        return (
            <EmptyOrgState
                title="Nenhuma organização ativa"
                message="Selecione ou vincule uma organização para acessar o dashboard tático de incursões."
            />
        );
    }
    const [utcTime, setUtcTime] = useState(new Date().toISOString().slice(11, 19));

    // Update UTC clock
    useEffect(() => {
        const interval = setInterval(() => {
            setUtcTime(new Date().toISOString().slice(11, 19));
        }, 1000);
        return () => clearInterval(interval);
    }, []);

    // Realtime subscription for new analyses
    const { status: realtimeStatus } = useRealtime(realtime.channel, {
        onInsert: (payload) => {
            const newIncursion = {
                id: payload.new.id,
                timestamp: payload.new.created_at,
                repository: payload.new.repository_full_name,
                prNumber: payload.new.pr_number,
                author: {
                    username: payload.new.author_username,
                    avatarUrl: payload.new.author_avatar_url,
                },
                verdict: payload.new.verdict,
                threatScore: payload.new.security_score,
                defconLevel: payload.new.defcon_level,
                executionTimeMs: payload.new.execution_time_ms,
                status: 'completed',
            };
            setFeed((prev) => [newIncursion, ...prev.slice(0, 19)]);
        },
    });

    return (
        <>
            <Head title="Dashboard" />
            <div className="space-y-6">
                {/* HUD Stats Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <HUDStat
                        label="PRs Abertas"
                        value={stats.totalOpenPRs}
                        icon={<GitBranch className="h-8 w-8 text-barrel" />}
                        trend="neutral"
                    />
                    <HUDStat
                        label="Threat Médio"
                        value={stats.avgThreatScore}
                        icon={<Activity className="h-8 w-8 text-barrel" />}
                        trend={stats.avgThreatScore > 50 ? 'up' : 'down'}
                        trendValue={stats.avgThreatScore > 50 ? '+alto' : '-baixo'}
                    />
                    <HUDStat
                        label="DEFCON Atual"
                        value={stats.currentDefcon}
                        icon={<Shield className="h-8 w-8 text-barrel" />}
                        trend="neutral"
                    />
                    <HUDStat
                        label="Tempo Médio Exec."
                        value={`${stats.avgExecutionTimeMs}ms`}
                        icon={<Clock className="h-8 w-8 text-barrel" />}
                        trend="neutral"
                    />
                </div>

                {/* Incursion Feed */}
                <div className="bg-plate border border-graphite rounded-lg overflow-hidden">
                    <div className="flex items-center justify-between p-4 border-b border-graphite">
                        <h2 className="text-lg font-mono font-bold text-bone">Feed Tático de Incursões</h2>
                        <StatusPill
                            status={realtimeStatus === 'connected' ? 'completed' : realtimeStatus === 'connecting' ? 'scanning' : 'failed'}
                            label={realtimeStatus === 'connected' ? 'LIVE' : realtimeStatus.toUpperCase()}
                        />
                    </div>
                    <div className="divide-y divide-graphite/50">
                        {feed.length > 0 ? (
                            feed.map((incursion) => (
                                <IncursionRow
                                    key={incursion.id}
                                    {...incursion}
                                    onClick={() => window.location.href = `/incursions/${incursion.id}`}
                                />
                            ))
                        ) : (
                            <div className="p-8 text-center text-dusk">
                                <Radio className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                <p className="font-mono">Nenhuma incursão registrada</p>
                                <p className="text-sm mt-1">Registre um repositório para começar</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}