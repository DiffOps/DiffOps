import { useState } from 'react';
import { usePage, Head, Link } from '@inertiajs/react';
import { ChevronLeft, GitBranch, User, Shield, Activity, Zap, AlertTriangle, CheckCircle, XCircle, Loader2, MessageSquare, RotateCcw, FileText, Download, Copy } from 'lucide-react';
import {
    Card,
    VerdictBadge,
    ThreatMeter,
    DefconMeter,
    Badge,
    Pill,
    DiffViewer,
    Button,
    HUDStat,
} from '@/components/Tactical';

const SEVERITY_COLORS = {
    critical: 'hostile',
    high: 'flagged',
    medium: 'neutral',
    low: 'clear',
};

const CATEGORY_LABELS = {
    secret: 'Segredo',
    downgrade: 'Downgrade',
    sensitive_file: 'Arquivo Sensível',
    eval: 'Execução Dinâmica',
    shell: 'Comando Shell',
    dependency: 'Dependência',
};

export default function IncursionShow() {
    const { analysis, repository } = usePage().props;
    const [activeTab, setActiveTab] = useState('diff');
    const [highlightFinding, setHighlightFinding] = useState(null);

    const diffLines = analysis.complianceChecks
        .filter((f) => f.filePath)
        .reduce((acc, finding) => {
            // Create mock diff lines from findings
            const lines = finding.description.split('\n').map((line, i) => ({
                type: i === 0 ? 'add' : 'context',
                content: `  ${line}`,
                lineNumber: { old: i + 1, new: i + 1 },
                findingId: `${finding.category}-${finding.filePath}-${i}`,
            }));
            return [...acc, ...lines];
        }, []);

    const tabs = [
        { id: 'diff', label: 'Diff Viewer', icon: FileText },
        { id: 'findings', label: 'Findings', icon: AlertTriangle },
        { id: 'risk', label: 'Risk Fingerprint', icon: Shield },
        { id: 'raw', label: 'Raw JSON', icon: Download },
    ];

    return (
        <>
            <Head title={`Incursão #${analysis.prNumber} - ${analysis.repository}`} />
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 p-4 bg-plate border border-graphite rounded-lg">
                    <div className="flex items-center gap-4">
                        <Link href="/incursions" className="p-2 text-dusk hover:text-bone hover:bg-steel rounded-lg transition-colors">
                            <ChevronLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-2 text-dusk font-mono text-sm">
                                <GitBranch className="h-4 w-4" />
                                <Link href={`/repos/${repository.id}`} className="hover:text-bone transition-colors">
                                    {analysis.repository}
                                </Link>
                                <span className="text-barrel">/</span>
                                <span className="text-bone font-bold">#{analysis.prNumber}</span>
                            </div>
                            <div className="flex items-center gap-2 mt-1">
                                {analysis.author?.avatarUrl && (
                                    <img src={analysis.author.avatarUrl} alt="" className="h-6 w-6 rounded-full" />
                                )}
                                <User className="h-4 w-4 text-barrel" />
                                <span className="font-mono text-sm">{analysis.author?.username}</span>
                                {analysis.author?.riskFingerprint && (
                                    <Badge variant={analysis.author.riskFingerprint.score > 70 ? 'hostile' : analysis.author.riskFingerprint.score > 35 ? 'flagged' : 'clear'}>
                                        Risk: {analysis.author.riskFingerprint.score}
                                    </Badge>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-4 lg:ml-auto">
                        <div className="hidden lg:flex items-center gap-6 text-right">
                            <HUDStat label="Threat Score" value={analysis.threatScore} trend="neutral" />
                            <HUDStat label="DEFCON" value={analysis.defconLevel} trend="neutral" />
                            <HUDStat label="Tempo Exec." value={`${analysis.executionTimeMs}ms`} trend="neutral" />
                        </div>
                        <div className="flex items-center gap-2">
                            <VerdictBadge verdict={analysis.verdict} size="lg" />
                        </div>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <div className="flex items-center gap-3">
                            <div className="p-3 bg-nv-green/10 rounded-lg">
                                <Activity className="h-6 w-6 text-nv-green" />
                            </div>
                            <div>
                                <p className="text-xs font-mono text-dusk uppercase">Veredito</p>
                                <p className="text-2xl font-mono font-bold text-bone capitalize">{analysis.verdict}</p>
                            </div>
                        </div>
                    </Card>
                    <Card>
                        <div className="flex items-center gap-3">
                            <div className="p-3 bg-amber/10 rounded-lg">
                                <Zap className="h-6 w-6 text-amber" />
                            </div>
                            <div>
                                <p className="text-xs font-mono text-dusk uppercase">Risk Level</p>
                                <p className="text-2xl font-mono font-bold text-bone capitalize">{analysis.riskLevel}</p>
                            </div>
                        </div>
                    </Card>
                    <Card>
                        <div className="flex items-center gap-3">
                            <div className="p-3 bg-comms-cyan/10 rounded-lg">
                                <Shield className="h-6 w-6 text-comms-cyan" />
                            </div>
                            <div>
                                <p className="text-xs font-mono text-dusk uppercase">DEFCON</p>
                                <p className="text-2xl font-mono font-bold text-bone">{analysis.defconLevel}</p>
                            </div>
                        </div>
                    </Card>
                    <Card>
                        <div className="flex items-center gap-3">
                            <div className="p-3 bg-defcon-red/10 rounded-lg">
                                <AlertTriangle className="h-6 w-6 text-defcon-red" />
                            </div>
                            <div>
                                <p className="text-xs font-mono text-dusk uppercase">Findings</p>
                                <p className="text-2xl font-mono font-bold text-bone">{analysis.findings.length}</p>
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Tabs */}
                <div className="bg-plate border border-graphite rounded-lg overflow-hidden">
                    <div className="border-b border-graphite">
                        <nav className="flex overflow-x-auto" aria-label="Tabs">
                            {tabs.map((tab) => (
                                <button
                                    key={tab.id}
                                    onClick={() => setActiveTab(tab.id)}
                                    className={`flex items-center gap-2 px-4 py-3 text-sm font-mono border-b-2 transition-colors ${
                                        activeTab === tab.id
                                            ? 'text-nv-green border-nv-green bg-nv-green/5'
                                            : 'text-dusk hover:text-bone hover:bg-steel border-transparent'
                                    }`}
                                >
                                    <tab.icon className="h-4 w-4" />
                                    {tab.label}
                                </button>
                            ))}
                        </nav>
                    </div>

                    <div className="p-6">
                        {activeTab === 'diff' && (
                            <DiffViewer
                                lines={diffLines.length > 0 ? diffLines : [
                                    { type: 'context', content: '// Nenhum diff disponível para esta análise', lineNumber: { old: 1, new: 1 } }
                                ]}
                                maxHeight="70vh"
                                highlightFinding={highlightFinding}
                                onFindingClick={setHighlightFinding}
                            />
                        )}

                        {activeTab === 'findings' && (
                            <div className="space-y-4">
                                {analysis.findings.length > 0 ? (
                                    analysis.findings.map((finding, index) => (
                                        <Card key={index} variant="outlined" className="border-l-4 border-l-current" style={{ borderColor: `var(--color-${SEVERITY_COLORS[finding.severity] || 'neutral'}, #64748b)` }}>
                                            <div className="flex items-start gap-4">
                                                <Badge variant={SEVERITY_COLORS[finding.severity] || 'neutral'} className="flex-shrink-0 mt-0.5">
                                                    {CATEGORY_LABELS[finding.category] || finding.category}
                                                </Badge>
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2 mb-2">
                                                        <Badge variant={SEVERITY_COLORS[finding.severity] || 'neutral'}>
                                                            {finding.severity.toUpperCase()}
                                                        </Badge>
                                                        <span className="font-mono text-sm text-dusk">{finding.filePath}</span>
                                                    </div>
                                                    <p className="text-bone font-mono text-sm whitespace-pre-wrap">{finding.description}</p>
                                                </div>
                                            </div>
                                        </Card>
                                    ))
                                ) : (
                                    <div className="text-center py-12 text-dusk">
                                        <CheckCircle className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                        <p className="font-mono">Nenhum finding detectado</p>
                                    </div>
                                )}
                            </div>
                        )}

                        {activeTab === 'risk' && (
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <Card>
                                    <h3 className="text-lg font-mono font-bold text-bone mb-4 flex items-center gap-2">
                                        <Shield className="h-5 w-5 text-nv-green" />
                                        Fingerprint de Risco do Contribuidor
                                    </h3>
                                    {analysis.author?.riskFingerprint ? (
                                        <div className="space-y-4">
                                            <div className="flex items-center justify-center">
                                                <ThreatMeter
                                                    score={analysis.author.riskFingerprint.score}
                                                    size={120}
                                                    strokeWidth={8}
                                                    showLabel
                                                />
                                            </div>
                                            <div className="grid grid-cols-2 gap-4 text-center">
                                                <div className="p-3 bg-asphalt rounded-lg">
                                                    <p className="text-2xl font-mono font-bold text-bone">{analysis.author.riskFingerprint.totalPrs}</p>
                                                    <p className="text-xs font-mono text-dusk">Total PRs</p>
                                                </div>
                                                <div className="p-3 bg-asphalt rounded-lg">
                                                    <p className="text-2xl font-mono font-bold text-amber">{analysis.author.riskFingerprint.flaggedPrs}</p>
                                                    <p className="text-xs font-mono text-dusk">Flagged</p>
                                                </div>
                                                <div className="p-3 bg-asphalt rounded-lg">
                                                    <p className="text-2xl font-mono font-bold text-defcon-red">{analysis.author.riskFingerprint.hostilePrs}</p>
                                                    <p className="text-xs font-mono text-dusk">Hostile</p>
                                                </div>
                                                <div className="p-3 bg-asphalt rounded-lg">
                                                    <p className="text-2xl font-mono font-bold text-bone">{analysis.author.riskFingerprint.avgFindingsPerPr}</p>
                                                    <p className="text-xs font-mono text-dusk">Avg Findings/PR</p>
                                                </div>
                                            </div>
                                            {analysis.author.riskFingerprint.isNewContributor && (
                                                <Badge variant="scanning" className="w-full text-center">
                                                    NOVO CONTRIBUIDOR
                                                </Badge>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="text-center py-8 text-dusk">
                                            <User className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                            <p className="font-mono">Dados de fingerprint indisponíveis</p>
                                        </div>
                                    )}
                                </Card>

                                <Card>
                                    <h3 className="text-lg font-mono font-bold text-bone mb-4 flex items-center gap-2">
                                        <Activity className="h-5 w-5 text-comms-cyan" />
                                        Resumo da Análise
                                    </h3>
                                    <div className="space-y-3 text-sm">
                                        <div className="flex justify-between">
                                            <span className="text-dusk">Tempo de Execução</span>
                                            <span className="font-mono text-bone">{analysis.executionTimeMs} ms</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-dusk">Head SHA</span>
                                            <span className="font-mono text-bone truncate max-w-[200px]">{analysis.headSha}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-dusk">Status</span>
                                            <span className="font-mono text-bone">{analysis.isDegraded ? 'Degradado (sem IA)' : 'Completo'}</span>
                                        </div>
                                        <div className="pt-4 border-t border-graphite">
                                            <p className="font-mono text-bone whitespace-pre-wrap">{analysis.summary}</p>
                                        </div>
                                    </div>
                                </Card>
                            </div>
                        )}

                        {activeTab === 'raw' && (
                            <Card variant="outlined">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-lg font-mono font-bold text-bone">JSON Bruto da Análise</h3>
                                    <Button variant="ghost" size="sm" leftIcon={<Copy />} onClick={() => navigator.clipboard.writeText(JSON.stringify(analysis, null, 2))}>
                                        Copiar JSON
                                    </Button>
                                </div>
                                <pre className="bg-obsidian border border-graphite rounded-lg p-4 max-h-[60vh] overflow-auto font-mono text-xs text-dusk">
                                    {JSON.stringify(analysis, null, 2)}
                                </pre>
                            </Card>
                        )}
                    </div>
                </div>

                {/* Actions Bar */}
                <div className="flex items-center justify-end gap-3 p-4 bg-plate border border-graphite rounded-lg sticky bottom-0">
                    <Button
                        variant="ghost"
                        leftIcon={<RotateCcw />}
                        onClick={() => window.location.href = `/incursions/${analysis.id}/rescan`}
                    >
                        Re-scan
                    </Button>
                    {repository.commentOnPr && (
                        <Button
                            variant="primary"
                            leftIcon={<MessageSquare />}
                            onClick={() => window.location.href = `/incursions/${analysis.id}/comment`}
                        >
                            Comentar no GitHub
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}