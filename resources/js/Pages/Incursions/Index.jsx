import { usePage, Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { GitBranch } from 'lucide-react';
import { IncursionRow, EmptyOrgState } from '@/components/Tactical';

export default function IncursionsIndex() {
    const { incursions, currentOrganization = null } = usePage().props;

    if (currentOrganization === null) {
        return (
            <EmptyOrgState
                title="Nenhuma organização ativa"
                message="Selecione ou vincule uma organização para visualizar as incursões."
            />
        );
    }

    return (
        <>
            <Head title="Incursões" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-mono font-bold text-bone">Incursões</h1>
                    <p className="text-dusk font-mono text-sm">Todas as análises da organização</p>
                </div>

                <div className="bg-plate border border-graphite rounded-lg overflow-hidden">
                    <div className="divide-y divide-graphite/50">
                        {(incursions.data ?? []).length > 0 ? (
                            incursions.data.map((incursion) => (
                                <IncursionRow
                                    key={incursion.id}
                                    {...incursion}
                                    onClick={() => window.location.href = `/incursions/${incursion.id}`}
                                />
                            ))
                        ) : (
                            <div className="p-8 text-center text-dusk">
                                <GitBranch className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                <p className="font-mono">Nenhuma incursão registrada</p>
                            </div>
                        )}
                    </div>

                    {(incursions.last_page ?? 1) > 1 && (
                        <div className="flex items-center justify-between px-4 py-4 border-t border-graphite">
                            <p className="text-sm font-mono text-dusk">
                                Página {incursions.current_page} de {incursions.last_page}
                            </p>
                            <div className="flex gap-2">
                                {incursions.prev_page_url && (
                                    <Link href={incursions.prev_page_url} className="px-3 py-1 text-sm font-mono text-dusk hover:text-bone hover:bg-steel rounded-lg border border-graphite transition-colors">
                                        Anterior
                                    </Link>
                                )}
                                {incursions.next_page_url && (
                                    <Link href={incursions.next_page_url} className="px-3 py-1 text-sm font-mono text-dusk hover:text-bone hover:bg-steel rounded-lg border border-graphite transition-colors">
                                        Próxima
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
