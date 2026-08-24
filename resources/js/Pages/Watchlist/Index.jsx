import { useState } from 'react';
import { usePage, Head } from '@inertiajs/react';
import { Eye, EyeOff, ExternalLink, Shield, AlertTriangle, CheckCircle, XCircle, Loader2, Radio, Plus } from 'lucide-react';
import { Link, useForm } from '@inertiajs/react';
import { Card, Button, StatusPill, VerdictBadge, Badge, Pill } from '@/components/Tactical';
import { useRealtime } from '@/hooks/useRealtime';

export default function WatchlistIndex() {
    const { watchlist, realtime } = usePage().props;
    const [showOnlyActive, setShowOnlyActive] = useState(false);

    const { status: realtimeStatus } = useRealtime(realtime.channel, {
        onUpdate: (payload) => {
            // In a real app, this would update the watchlist item optimistically
        },
    });

    const filteredWatchlist = showOnlyActive
        ? watchlist.filter((r) => r.is_active)
        : watchlist;

    const handleToggle = (repoId) => {
        const { post } = useForm({});
        post(route('watchlist.toggle', repoId), {
            onSuccess: () => window.location.reload(),
        });
    };

    return (
        <>
            <Head title="Watchlist" />
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-mono font-bold text-bone">Watchlist Tática</h1>
                        <p className="text-dusk font-mono text-sm">Repositórios monitorados em tempo real</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <label className="flex items-center gap-2 text-sm font-mono text-dusk">
                            <input
                                type="checkbox"
                                checked={showOnlyActive}
                                onChange={(e) => setShowOnlyActive(e.target.checked)}
                                className="w-4 h-4 bg-obsidian border-graphite rounded text-nv-green focus:ring-nv-green"
                            />
                            Apenas ativos
                        </label>
                    </div>
                </div>

                {/* Realtime Status */}
                <div className="flex items-center gap-3 p-3 bg-plate border border-graphite rounded-lg">
                    <div className="flex items-center gap-2">
                        <StatusPill
                            status={realtimeStatus === 'connected' ? 'completed' : realtimeStatus === 'connecting' ? 'scanning' : 'failed'}
                            label={realtimeStatus === 'connected' ? 'LIVE' : realtimeStatus.toUpperCase()}
                        />
                        <span className="font-mono text-sm text-dusk">Conexão Realtime</span>
                    </div>
                    <div className="flex-1" />
                    <span className="text-xs font-mono text-dusk">
                        {watchlist.length} repositórios na watchlist
                    </span>
                </div>

                {/* Watchlist Grid */}
                {filteredWatchlist.length > 0 ? (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {filteredWatchlist.map((repo) => (
                            <Card key={repo.id} variant="outlined" className="relative overflow-hidden transition-all hover:border-comms-cyan/50">
                                <div className="absolute top-3 right-3">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className={repo.is_active ? 'text-nv-green' : 'text-dusk'}
                                        onClick={() => handleToggle(repo.id)}
                                    >
                                        {repo.is_active ? (
                                            <>
                                                <Eye className="h-4 w-4" /> Monitorando
                                            </>
                                        ) : (
                                            <>
                                                <EyeOff className="h-4 w-4" /> Parado
                                            </>
                                        )}
                                    </Button>
                                </div>
                                <div className="p-4">
                                    <div className="flex items-start justify-between gap-4 mb-4">
                                        <Link href={`/repos/${repo.id}`} className="flex-1 min-w-0">
                                            <ExternalLink className="h-4 w-4 text-barrel mb-1" />
                                            <p className="font-mono text-bone truncate">{repo.full_name}</p>
                                            <p className="text-xs font-mono text-dusk truncate">{repo.html_url}</p>
                                        </Link>
                                        <div className="flex flex-col items-end gap-1">
                                            <VerdictBadge verdict={repo.last_incursion?.verdict || 'clear'} size="sm" />
                                            <StatusPill
                                                status={repo.is_active ? 'completed' : 'idle'}
                                                label={repo.is_active ? 'ATIVO' : 'INATIVO'}
                                            />
                                        </div>
                                    </div>

                                    {repo.last_incursion ? (
                                        <div className="pt-4 border-t border-graphite space-y-2">
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="font-mono text-dusk">Última Incursão</span>
                                                <span className="font-mono text-bone">
                                                    {new Date(repo.last_incursion.timestamp).toLocaleString('pt-BR', { hour12: false })}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <VerdictBadge verdict={repo.last_incursion.verdict} size="sm" />
                                                <span className="font-mono text-xs text-dusk">
                                                    Score: {repo.last_incursion.threatScore} | DEFCON {repo.last_incursion.defconLevel}
                                                </span>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="pt-4 border-t border-graphite text-center text-dusk py-4">
                                            <Radio className="h-8 w-8 mx-auto mb-2 opacity-50" />
                                            <p className="font-mono text-sm">Nenhuma incursão registrada</p>
                                        </div>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Card className="text-center py-12">
                        <Radio className="h-16 w-16 mx-auto mb-4 opacity-50" />
                        <h3 className="text-lg font-mono font-bold text-bone mb-2">Watchlist vazia</h3>
                        <p className="text-dusk mb-4">Adicione repositórios à watchlist na página de Repositórios</p>
                        <Link href="/repos">
                            <Button variant="primary" leftIcon={<Plus />}>
                                Ir para Repositórios
                            </Button>
                        </Link>
                    </Card>
                )}
            </div>
        </>
    );
}