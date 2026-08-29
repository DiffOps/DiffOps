import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export function OrgSwitcher({ organizations = [], currentOrganization = null, onSwitch }) {
    if (!organizations || organizations.length === 0) {
        return (
            <div className="px-3 py-2 text-xs font-mono text-dusk border border-graphite rounded-lg bg-plate">
                Nenhuma organização
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-1">
            <span className="text-[10px] font-mono text-dusk uppercase tracking-wider">
                Organização ativa
            </span>
            <div className="flex flex-col gap-1">
                {organizations.map((org) => {
                    const active = currentOrganization && currentOrganization.id === org.id;

                    return (
                        <button
                            key={org.id}
                            type="button"
                            onClick={() => onSwitch && onSwitch(org.id)}
                            aria-current={active ? 'true' : undefined}
                            className={cn(
                                'flex items-center justify-between gap-2 px-3 py-2 text-sm font-mono rounded-lg border transition-colors',
                                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-comms-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-asphalt',
                                active
                                    ? 'border-nv-green/40 bg-nv-green/10 text-nv-green'
                                    : 'border-graphite text-bone hover:bg-plate hover:border-barrel'
                            )}
                        >
                            <span className="truncate">{org.name}</span>
                            {active && <Check className="h-4 w-4 flex-shrink-0" aria-hidden="true" />}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
