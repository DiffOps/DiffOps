import { useState } from 'react';
import { usePage, Head, Link, useForm } from '@inertiajs/react';
import { Calendar, Filter, Download, Search, ChevronDown, ChevronUp, AlertTriangle, CheckCircle, XCircle, Loader2, User, Database, GitBranch, FileText, Shield, Plus, Edit, Trash2, MessageSquare, RotateCcw } from 'lucide-react';
import { Card, Button, Badge, Pill, StatusPill } from '@/components/Tactical';

const ACTION_ICONS = {
    created: { icon: Plus, color: 'nv-green' },
    updated: { icon: Edit, color: 'amber' },
    deleted: { icon: Trash2, color: 'defcon-red' },
    analyzed: { icon: Shield, color: 'comms-cyan' },
    commented: { icon: MessageSquare, color: 'comms-cyan' },
    synced: { icon: RotateCcw, color: 'nv-green' },
    failed: { icon: XCircle, color: 'defcon-red' },
    scanned: { icon: Search, color: 'comms-cyan' },
};

const ENTITY_ICONS = {
    pull_request: GitBranch,
    repository: Database,
    analysis: FileText,
    user: User,
    organization: Shield,
};

export default function OperationsLogIndex() {
    const { logs, filters } = usePage().props;
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [actionFilter, setActionFilter] = useState('');
    const [entityFilter, setEntityFilter] = useState('');
    const [userFilter, setUserFilter] = useState('');
    const [search, setSearch] = useState('');

    const { data, setData, get, processing } = useForm({
        date_from: dateFrom,
        date_to: dateTo,
        action: actionFilter,
        entity_type: entityFilter,
        user_id: userFilter,
        search: search,
    });

    const handleFilter = (e) => {
        e.preventDefault();
        get(route('operations-log.index'), { preserveScroll: true });
    };

    const handleExport = () => {
        const params = new URLSearchParams({
            date_from: dateFrom,
            date_to: dateTo,
            action: actionFilter,
            entity_type: entityFilter,
            user_id: userFilter,
            search: search,
        });
        window.location.href = `${route('operations-log.export')}?${params.toString()}`;
    };

    const getActionBadge = (action) => {
        const config = ACTION_ICONS[action] || { icon: AlertTriangle, color: 'neutral' };
        const Icon = config.icon;
        return (
            <Badge variant={config.color} className="flex items-center gap-1">
                <Icon className="h-3 w-3" />
                {action.toUpperCase()}
            </Badge>
        );
    };

    const getEntityBadge = (entity) => {
        const Icon = ENTITY_ICONS[entity] || Database;
        return (
            <Pill variant="dusk" className="flex items-center gap-1">
                <Icon className="h-3 w-3" />
                {entity.replace('_', ' ').toUpperCase()}
            </Pill>
        );
    };

    return (
        <>
            <Head title="Combat History" />
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-mono font-bold text-bone">Combat History</h1>
                        <p className="text-dusk font-mono text-sm">Log de auditoria completo de operações</p>
                    </div>
                    <Button variant="primary" leftIcon={<Download />} onClick={handleExport} disabled={processing}>
                        Exportar CSV
                    </Button>
                </div>

                {/* Filters */}
                <Card className="p-4">
                    <form onSubmit={handleFilter} className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                            <div>
                                <label className="block text-xs font-mono text-dusk mb-1">Data Início</label>
                                <input
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) => setDateFrom(e.target.value)}
                                    className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone placeholder-dusk focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-mono text-dusk mb-1">Data Fim</label>
                                <input
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                    className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone placeholder-dusk focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-mono text-dusk mb-1">Ação</label>
                                <select
                                    value={actionFilter}
                                    onChange={(e) => setActionFilter(e.target.value)}
                                    className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                                >
                                    <option value="">Todas</option>
                                    {filters.actions.map((a) => (
                                        <option key={a} value={a}>{a.toUpperCase()}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-mono text-dusk mb-1">Entidade</label>
                                <select
                                    value={entityFilter}
                                    onChange={(e) => setEntityFilter(e.target.value)}
                                    className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                                >
                                    <option value="">Todas</option>
                                    {filters.entityTypes.map((e) => (
                                        <option key={e} value={e}>{e.replace('_', ' ').toUpperCase()}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-mono text-dusk mb-1">Usuário</label>
                                <input
                                    type="text"
                                    value={userFilter}
                                    onChange={(e) => setUserFilter(e.target.value)}
                                    placeholder="Username"
                                    className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone placeholder-dusk focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-mono text-dusk mb-1">Busca</label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-barrel" />
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Buscar..."
                                        className="w-full pl-10 px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone placeholder-dusk focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                                    />
                                </div>
                            </div>
                        </div>
                        <div className="flex gap-3 pt-2">
                            <Button type="submit" variant="primary" leftIcon={<Filter />} disabled={processing}>
                                {processing ? 'Filtrando...' : 'Aplicar Filtros'}
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => {
                                setDateFrom(''); setDateTo(''); setActionFilter(''); setEntityFilter(''); setUserFilter(''); setSearch('');
                                get(route('operations-log.index'), { preserveScroll: true });
                            }}>
                                Limpar
                            </Button>
                        </div>
                    </form>
                </Card>

                {/* Logs Table */}
                <Card>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-graphite text-left text-xs font-mono text-dusk uppercase">
                                    <th className="pb-3 px-4">Timestamp</th>
                                    <th className="pb-3 px-4">Ação</th>
                                    <th className="pb-3 px-4">Entidade</th>
                                    <th className="pb-3 px-4">ID</th>
                                    <th className="pb-3 px-4 hidden md:table-cell">Usuário</th>
                                    <th className="pb-3 px-4 hidden lg:table-cell">Payload</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-graphite/50">
                                {logs.data.length > 0 ? (
                                    logs.data.map((log) => (
                                        <tr key={log.id} className="hover:bg-steel/50 transition-colors">
                                            <td className="py-3 px-4 font-mono text-xs text-dusk whitespace-nowrap">
                                                {new Date(log.timestamp).toLocaleString('pt-BR', { hour12: false })}
                                            </td>
                                            <td className="py-3 px-4">
                                                {getActionBadge(log.action)}
                                            </td>
                                            <td className="py-3 px-4">
                                                {getEntityBadge(log.entity_type)}
                                            </td>
                                            <td className="py-3 px-4 font-mono text-xs text-bone truncate max-w-[150px]">
                                                {log.entity_id?.slice(0, 8) || '—'}
                                            </td>
                                            <td className="py-3 px-4 hidden md:table-cell">
                                                {log.user ? (
                                                    <div className="flex items-center gap-2">
                                                        {log.user.avatar_url && <img src={log.user.avatar_url} alt="" className="h-5 w-5 rounded-full" />}
                                                        <span className="font-mono text-sm text-bone">{log.user.username}</span>
                                                    </div>
                                ) : (
                                    <span className="text-barrel font-mono text-xs">system</span>
                                )}
                                            </td>
                                            <td className="py-3 px-4 hidden lg:table-cell">
                                                <details className="font-mono text-[10px] text-dusk">
                                                    <summary className="cursor-pointer hover:text-bone">Ver payload</summary>
                                                    <pre className="mt-1 p-2 bg-obsidian border border-graphite rounded text-dusk overflow-x-auto max-h-32">{JSON.stringify(log.payload, null, 2)}</pre>
                                                </details>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="py-12 text-center text-dusk">
                                            <div className="flex flex-col items-center gap-3">
                                                <div className="p-3 bg-asphalt rounded-full">
                                                    <Database className="h-8 w-8 text-barrel" />
                                                </div>
                                                <p className="font-mono">Nenhum log encontrado</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>

                        {/* Pagination */}
                        {logs.last_page > 1 && (
                            <div className="flex items-center justify-between px-4 py-4 border-t border-graphite">
                                <p className="text-sm font-mono text-dusk">
                                    Mostrando {logs.from} a {logs.to} de {logs.total} registros
                                </p>
                                <div className="flex gap-2">
                                    {logs.prev_page_url && (
                                        <a href={logs.prev_page_url} className="px-3 py-1 text-sm font-mono text-dusk hover:text-bone hover:bg-steel rounded-lg border border-graphite transition-colors">
                                            Anterior
                                        </a>
                                    )}
                                    {logs.next_page_url && (
                                        <a href={logs.next_page_url} className="px-3 py-1 text-sm font-mono text-dusk hover:text-bone hover:bg-steel rounded-lg border border-graphite transition-colors">
                                            Próxima
                                        </a>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </Card>
            </div>
        </>
    );
}