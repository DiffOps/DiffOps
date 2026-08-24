import { useState } from 'react';
import { usePage, Head, Link } from '@inertiajs/react';
import { Plus, Search, Filter, ExternalLink, Settings, Wifi, WifiOff, AlertTriangle, CheckCircle, XCircle, Trash2, Edit, Loader2, Copy, X } from 'lucide-react';
import { Card, Button, Badge, StatusPill, Pill } from '@/components/Tactical';

export default function RepositoriesIndex() {
    const { repositories, webhookUrl } = usePage().props;
    const [showModal, setShowModal] = useState(false);
    const [formData, setFormData] = useState({ github_repo_id: '', installation_id: '' });
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState({});

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        try {
            const response = await fetch('/repos', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(formData),
            });

            if (!response.ok) {
                const data = await response.json();
                throw data;
            }

            window.location.reload();
        } catch (err) {
            setErrors(err.errors || { general: err.message });
        } finally {
            setLoading(false);
        }
    };

    const handleDelete = async (repoId) => {
        if (!confirm('Tem certeza que deseja remover este repositório?')) return;

        try {
            const response = await fetch(`/repos/${repoId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });

            if (!response.ok) throw new Error('Falha ao remover');
            window.location.reload();
        } catch (err) {
            alert('Erro ao remover repositório');
        }
    };

    const toggleActive = async (repoId, currentStatus) => {
        try {
            const response = await fetch(`/repos/${repoId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ is_active: !currentStatus }),
            });

            if (!response.ok) throw new Error('Falha ao atualizar');
            window.location.reload();
        } catch (err) {
            alert('Erro ao atualizar status');
        }
    };

    return (
        <>
            <Head title="Repositórios" />
            <div className="space-y-6">
                {/* Header & Actions */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-mono font-bold text-bone">Repositórios</h1>
                        <p className="text-dusk font-mono text-sm">Gerencie repositórios monitorados</p>
                    </div>
                    <Button onClick={() => setShowModal(true)} leftIcon={<Plus />}>
                        Adicionar Repositório
                    </Button>
                </div>

                {/* Webhook Info */}
                <Card variant="outlined" className="border-comms-cyan/30">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <Wifi className="h-6 w-6 text-comms-cyan" />
                            <div>
                                <p className="font-mono text-sm text-bone">Webhook URL</p>
                                <code className="font-mono text-xs text-dusk bg-obsidian px-2 py-1 rounded">{webhookUrl}</code>
                            </div>
                        </div>
                        <Button variant="ghost" size="sm" leftIcon={<Copy />} onClick={() => navigator.clipboard.writeText(webhookUrl)}>
                            Copiar
                        </Button>
                    </div>
                </Card>

                {/* Repositories Table */}
                <Card>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-graphite text-left text-xs font-mono text-dusk uppercase">
                                    <th className="pb-3 px-4">Repositório</th>
                                    <th className="pb-3 px-4 hidden md:table-cell">Owner</th>
                                    <th className="pb-3 px-4 hidden lg:table-cell">Status</th>
                                    <th className="pb-3 px-4 hidden lg:table-cell">Comment PR</th>
                                    <th className="pb-3 px-4 hidden lg:table-cell">Escalate</th>
                                    <th className="pb-3 px-4 hidden lg:table-cell">Webhook</th>
                                    <th className="pb-3 px-4 hidden lg:table-cell">Última Incursão</th>
                                    <th className="pb-3 px-4">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-graphite/50">
                                {repositories.data.length > 0 ? (
                                    repositories.data.map((repo) => (
                                        <tr key={repo.id} className="hover:bg-steel/50 transition-colors">
                                            <td className="py-4 px-4">
                                                <Link href={`/repos/${repo.id}`} className="flex items-center gap-3">
                                                    <ExternalLink className="h-4 w-4 text-barrel" />
                                                    <div>
                                                        <p className="font-mono text-bone truncate max-w-xs">{repo.full_name}</p>
                                                        <p className="text-[10px] text-dusk">ID: {repo.github_repo_id}</p>
                                                    </div>
                                                </Link>
                                            </td>
                                            <td className="py-4 px-4 hidden md:table-cell font-mono text-sm text-bone">{repo.owner_login}</td>
                                            <td className="py-4 px-4 hidden lg:table-cell">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className={repo.is_active ? 'text-nv-green' : 'text-dusk'}
                                                    onClick={() => toggleActive(repo.id, repo.is_active)}
                                                >
                                                    {repo.is_active ? (
                                                        <>
                                                            <CheckCircle className="h-4 w-4" /> Ativo
                                                        </>
                                                    ) : (
                                                        <>
                                                            <AlertTriangle className="h-4 w-4" /> Inativo
                                                        </>
                                                    )}
                                                </Button>
                                            </td>
                                            <td className="py-4 px-4 hidden lg:table-cell">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className={repo.comment_on_pr ? 'text-nv-green' : 'text-dusk'}
                                                    onClick={() => toggleActive(repo.id, repo.comment_on_pr)}
                                                >
                                                    {repo.comment_on_pr ? (
                                                        <>
                                                            <CheckCircle className="h-4 w-4" /> Sim
                                                        </>
                                                    ) : (
                                                        <>
                                                            <XCircle className="h-4 w-4" /> Não
                                                        </>
                                                    )}
                                                </Button>
                                            </td>
                                            <td className="py-4 px-4 hidden lg:table-cell">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className={repo.escalate_on_hostile ? 'text-amber' : 'text-dusk'}
                                                    onClick={() => toggleActive(repo.id, repo.escalate_on_hostile)}
                                                >
                                                    {repo.escalate_on_hostile ? (
                                                        <>
                                                            <AlertTriangle className="h-4 w-4" /> Sim
                                                        </>
                                                    ) : (
                                                        <>
                                                            <XCircle className="h-4 w-4" /> Não
                                                        </>
                                                    )}
                                                </Button>
                                            </td>
                                            <td className="py-4 px-4 hidden lg:table-cell">
                                                <StatusPill
                                                    status={repo.webhook_status === 'connected' ? 'completed' : repo.webhook_status === 'error' ? 'failed' : 'idle'}
                                                    label={repo.webhook_status === 'connected' ? 'Conectado' : repo.webhook_status === 'error' ? 'Erro' : 'Pendente'}
                                                />
                                            </td>
                                            <td className="py-4 px-4 hidden lg:table-cell font-mono text-xs text-dusk">
                                                {repo.last_incursion_at ? new Date(repo.last_incursion_at).toLocaleString('pt-BR', { hour12: false }) : '—'}
                                            </td>
                                            <td className="py-4 px-4">
                                                <div className="flex items-center gap-2">
                                                    <Link href={`/repos/${repo.id}`} className="p-2 text-dusk hover:text-bone hover:bg-steel rounded-lg transition-colors" title="Ver detalhes">
                                                        <Settings className="h-4 w-4" />
                                                    </Link>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-defcon-red hover:text-defcon-red/80"
                                                        onClick={() => handleDelete(repo.id)}
                                                        disabled={loading}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={8} className="py-12 text-center text-dusk">
                                            <div className="flex flex-col items-center gap-3">
                                                <div className="p-3 bg-asphalt rounded-full">
                                                    <Loader2 className="h-8 w-8 text-barrel" />
                                                </div>
                                                <p className="font-mono">Nenhum repositório registrado</p>
                                                <p className="text-sm">Clique em "Adicionar Repositório" para começar</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>

                        {/* Pagination */}
                        {repositories.last_page > 1 && (
                            <div className="flex items-center justify-between px-4 py-4 border-t border-graphite">
                                <p className="text-sm font-mono text-dusk">
                                    Mostrando {repositories.from} a {repositories.to} de {repositories.total} repositórios
                                </p>
                                <div className="flex gap-2">
                                    {repositories.prev_page_url && (
                                        <Link href={repositories.prev_page_url} className="px-3 py-1 text-sm font-mono text-dusk hover:text-bone hover:bg-steel rounded-lg border border-graphite transition-colors">
                                            Anterior
                                        </Link>
                                    )}
                                    {repositories.next_page_url && (
                                        <Link href={repositories.next_page_url} className="px-3 py-1 text-sm font-mono text-dusk hover:text-bone hover:bg-steel rounded-lg border border-graphite transition-colors">
                                            Próxima
                                        </Link>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </Card>

                {/* Add Repository Modal */}
                {showModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-obsidian/80">
                        <Card className="w-full max-w-md mx-4" variant="elevated">
                            <div className="flex items-center justify-between mb-6">
                                <h2 className="text-lg font-mono font-bold text-bone">Adicionar Repositório</h2>
                                <button onClick={() => setShowModal(false)} className="p-1 text-dusk hover:text-bone">
                                    <X className="h-5 w-5" />
                                </button>
                            </div>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <label htmlFor="github_repo_id" className="block text-sm font-mono text-dusk mb-1">GitHub Repository ID</label>
                                    <input
                                        type="number"
                                        id="github_repo_id"
                                        name="github_repo_id"
                                        value={formData.github_repo_id}
                                        onChange={(e) => setFormData({ ...formData, github_repo_id: e.target.value })}
                                        className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone placeholder-dusk focus:outline-none focus:ring-2 focus:ring-comms-cyan focus:border-transparent"
                                        placeholder="Ex: 123456789"
                                        required
                                    />
                                    {errors.github_repo_id && <p className="mt-1 text-sm text-defcon-red">{errors.github_repo_id[0]}</p>}
                                </div>
                                <div>
                                    <label htmlFor="installation_id" className="block text-sm font-mono text-dusk mb-1">Installation ID (opcional)</label>
                                    <input
                                        type="number"
                                        id="installation_id"
                                        name="installation_id"
                                        value={formData.installation_id}
                                        onChange={(e) => setFormData({ ...formData, installation_id: e.target.value })}
                                        className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone placeholder-dusk focus:outline-none focus:ring-2 focus:ring-comms-cyan focus:border-transparent"
                                        placeholder="Ex: 987654321"
                                    />
                                </div>
                                <div className="flex gap-3 pt-4">
                                    <Button type="button" variant="ghost" onClick={() => setShowModal(false)} className="flex-1">
                                        Cancelar
                                    </Button>
                                    <Button type="submit" loading={loading} className="flex-1">
                                        {loading ? 'Adicionando...' : 'Adicionar'}
                                    </Button>
                                </div>
                            </form>
                        </Card>
                    </div>
                )}
            </div>
        </>
    );
}