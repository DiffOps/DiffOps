import { Card } from './Card';

export function EmptyOrgState({ title, message }) {
    return (
        <Card className="text-center">
            <div className="flex flex-col items-center gap-3 py-6">
                <div className="h-12 w-12 rounded-full bg-plate border border-graphite flex items-center justify-center">
                    <span className="text-nv-green font-mono text-xl">!</span>
                </div>
                <h2 className="text-lg font-mono font-bold text-bone">{title}</h2>
                {message ? (
                    <p className="text-sm text-dusk max-w-md font-mono">{message}</p>
                ) : null}
            </div>
        </Card>
    );
}
