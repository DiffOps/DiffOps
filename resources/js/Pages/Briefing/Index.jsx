import { useState, useMemo } from 'react';
import { usePage, Head } from '@inertiajs/react';
import {
    Calendar, TrendingUp, TrendingDown, BarChart2, PieChart, Activity,
    AlertTriangle, CheckCircle, XCircle, Shield, Zap, Download
} from 'lucide-react';
import {
    Card, Badge, VerdictBadge, ThreatMeter, DefconMeter, Pill, HUDStat, EmptyOrgState
} from '@/components/Tactical';
import {
    PieChart as RechartsPieChart, Pie, Cell, BarChart, Bar, XAxis, YAxis, CartesianGrid,
    Tooltip, Legend, ResponsiveContainer, LineChart, Line
} from 'recharts';

const VERDICT_COLORS = { clear: '#22c55e', flagged: '#f59e0b', hostile: '#ef4444' };
const SEVERITY_COLORS = { critical: '#ef4444', high: '#f59e0b', medium: '#f59e0b', low: '#22c55e' };

export default function BriefingIndex() {
    const { period, verdictDistribution, threatHistogram, defconTrend, findingsByCategory, topRepos, currentOrganization = null } = usePage().props;

    if (currentOrganization === null) {
        return (
            <EmptyOrgState
                title="Nenhuma organização ativa"
                message="Selecione ou vincule uma organização para gerar o battle briefing de analytics."
            />
        );
    }
    const [days, setDays] = useState(period.days);

    const handlePeriodChange = (newDays) => {
        setDays(newDays);
        // In a real app, this would fetch new data via Inertia visit
    };

    const verdictData = useMemo(() => Object.entries(verdictDistribution).map(([name, value]) => ({
        name: name.toUpperCase(),
        value,
        color: VERDICT_COLORS[name] || '#64748b',
    })), [verdictDistribution]);

    const threatData = useMemo(() => threatHistogram.map((h) => ({
        range: h.range,
        count: h.count,
    })), [threatHistogram]);

    const defconData = useMemo(() => defconTrend.map((d) => ({
        date: new Date(d.date).toLocaleDateString('pt-BR', { month: 'short', day: 'numeric' }),
        avgDefcon: d.avg_defcon,
        avgTime: d.avg_execution_time_ms,
    })), [defconTrend]);

    const findingsData = useMemo(() => {
        const data = [];
        Object.entries(findingsByCategory).forEach(([category, severities]) => {
            Object.entries(severities).forEach(([severity, count]) => {
                data.push({ category, severity, count, color: SEVERITY_COLORS[severity] || '#64748b' });
            });
        });
        return data;
    }, [findingsByCategory]);

    const totalAnalyses = verdictData.reduce((sum, d) => sum + d.value, 0);

    return (
        <>
            <Head title="Briefing - Analytics" />
            <div className="space-y-6">
                {/* Header & Period Selector */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-mono font-bold text-bone">Battle Briefing</h1>
                        <p className="text-dusk font-mono text-sm">Analytics e inteligência tática</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <label className="flex items-center gap-2 text-sm font-mono text-dusk">
                            <Calendar className="h-4 w-4" />
                            Período:
                        </label>
                        <select
                            value={days}
                            onChange={(e) => handlePeriodChange(parseInt(e.target.value))}
                            className="px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                        >
                            <option value={7}>Últimos 7 dias</option>
                            <option value={30}>Últimos 30 dias</option>
                            <option value={90}>Últimos 90 dias</option>
                        </select>
                    </div>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <HUDStat
                        label="Total Análises"
                        value={totalAnalyses}
                        icon={<Activity className="h-8 w-8 text-barrel" />}
                        trend="neutral"
                    />
                    <HUDStat
                        label="CLEAR"
                        value={verdictDistribution.clear || 0}
                        icon={<CheckCircle className="h-8 w-8 text-nv-green" />}
                        trend="neutral"
                    />
                    <HUDStat
                        label="FLAGGED"
                        value={verdictDistribution.flagged || 0}
                        icon={<AlertTriangle className="h-8 w-8 text-amber" />}
                        trend="neutral"
                    />
                    <HUDStat
                        label="HOSTILE"
                        value={verdictDistribution.hostile || 0}
                        icon={<XCircle className="h-8 w-8 text-defcon-red" />}
                        trend="neutral"
                    />
                </div>

                {/* Charts Row 1: Verdict Distribution + Threat Histogram */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Card>
                        <h3 className="text-lg font-mono font-bold text-bone mb-4 flex items-center gap-2">
                            <PieChart className="h-5 w-5 text-comms-cyan" />
                            Distribuição de Vereditos
                        </h3>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <RechartsPieChart>
                                    <Pie
                                        data={verdictData}
                                        cx="50%"
                                        cy="50%"
                                        innerRadius={60}
                                        outerRadius={100}
                                        paddingAngle={2}
                                        dataKey="value"
                                        nameKey="name"
                                        label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                                        labelLine={false}
                                    >
                                        {verdictData.map((entry, index) => (
                                            <Cell key={`cell-${index}`} fill={entry.color} />
                                        ))}
                                    </Pie>
                                    <Tooltip formatter={(value) => [value, 'análises']} />
                                </RechartsPieChart>
                            </ResponsiveContainer>
                        </div>
                    </Card>

                    <Card>
                        <h3 className="text-lg font-mono font-bold text-bone mb-4 flex items-center gap-2">
                            <BarChart2 className="h-5 w-5 text-comms-cyan" />
                            Histograma de Threat Score
                        </h3>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={threatData} layout="vertical">
                                    <CartesianGrid strokeDasharray="3 3" stroke="#24303e" />
                                    <XAxis type="number" tick={{ fontSize: 11, fill: '#94a3b8' }} />
                                    <YAxis type="category" dataKey="range" tick={{ fontSize: 11, fill: '#94a3b8' }} width={60} />
                                    <Tooltip formatter={(value) => [value, 'análises']} />
                                    <Bar dataKey="count" fill="#38bdf8" radius={[0, 4, 4, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </Card>
                </div>

                {/* Charts Row 2: DEFCON Trend + Findings Heatmap */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Card>
                        <h3 className="text-lg font-mono font-bold text-bone mb-4 flex items-center gap-2">
                            <Activity className="h-5 w-5 text-comms-cyan" />
                            Tendência DEFCON & Tempo de Execução
                        </h3>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={defconData}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#24303e" />
                                    <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#94a3b8' }} />
                                    <YAxis yAxisId="left" tick={{ fontSize: 11, fill: '#94a3b8' }} domain={[1, 5]} reverse />
                                    <YAxis yAxisId="right" orientation="right" tick={{ fontSize: 11, fill: '#94a3b8' }} />
                                    <Tooltip />
                                    <Legend />
                                    <Line
                                        yAxisId="left"
                                        type="monotone"
                                        dataKey="avgDefcon"
                                        stroke="#ef4444"
                                        strokeWidth={2}
                                        dot={false}
                                        name="DEFCON Médio"
                                    />
                                    <Line
                                        yAxisId="right"
                                        type="monotone"
                                        dataKey="avgTime"
                                        stroke="#38bdf8"
                                        strokeWidth={2}
                                        dot={false}
                                        name="Tempo Exec (ms)"
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </div>
                    </Card>

                    <Card>
                        <h3 className="text-lg font-mono font-bold text-bone mb-4 flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-amber" />
                            Findings por Categoria & Severidade
                        </h3>
                        <div className="space-y-3">
                            {Object.entries(findingsByCategory).map(([category, severities]) => (
                                <div key={category} className="space-y-2">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="font-mono text-bone capitalize">{category.replace('_', ' ')}</span>
                                        <span className="font-mono text-dusk">
                                            {Object.values(severities).reduce((a, b) => a + b, 0)} total
                                        </span>
                                    </div>
                                    <div className="flex gap-2">
                                        {Object.entries(severities).map(([severity, count]) => (
                                            <Badge key={severity} variant={['critical', 'high'].includes(severity) ? 'hostile' : severity === 'medium' ? 'flagged' : 'clear'} className="flex-1 text-center">
                                                {severity.toUpperCase()}: {count}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>

                {/* Top Repos */}
                <Card>
                    <h3 className="text-lg font-mono font-bold text-bone mb-4 flex items-center gap-2">
                        <Zap className="h-5 w-5 text-defcon-red" />
                        Top 10 Repositórios por Incursões
                    </h3>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-graphite text-left text-xs font-mono text-dusk uppercase">
                                    <th className="pb-3 px-4">Repositório</th>
                                    <th className="pb-3 px-4 text-right">Total</th>
                                    <th className="pb-3 px-4 text-right">Hostile</th>
                                    <th className="pb-3 px-4 text-right">Flagged</th>
                                    <th className="pb-3 px-4 text-right">Clear</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-graphite/50">
                                {topRepos.map((repo, index) => (
                                    <tr key={index} className="hover:bg-steel/50">
                                        <td className="py-3 px-4 font-mono text-sm text-bone truncate max-w-[300px]">{repo.repo}</td>
                                        <td className="py-3 px-4 text-right font-mono text-bone">{repo.count}</td>
                                        <td className="py-3 px-4 text-right font-mono text-defcon-red">{repo.hostile}</td>
                                        <td className="py-3 px-4 text-right font-mono text-amber">{repo.flagged}</td>
                                        <td className="py-3 px-4 text-right font-mono text-nv-green">{repo.count - repo.hostile - repo.flagged}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </>
    );
}