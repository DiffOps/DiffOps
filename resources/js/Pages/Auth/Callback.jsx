import { useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import { Loader2, ShieldCheck } from 'lucide-react';
import { exchangeAndBridgeSession } from '@/lib/supabase';

export default function Callback() {
    useEffect(() => {
        let cancelled = false;

        exchangeAndBridgeSession().then((ok) => {
            if (!cancelled) {
                router.replace(ok ? '/dashboard' : '/login');
            }
        });

        return () => {
            cancelled = true;
        };
    }, []);

    return (
        <>
            <Head title="Autenticando — DiffOps" />
            <div className="min-h-screen flex flex-col items-center justify-center gap-4 bg-obsidian">
                <ShieldCheck className="h-10 w-10 text-nv-green" />
                <p className="font-mono text-sm text-dusk tracking-widest uppercase">Autenticando</p>
                <Loader2 className="h-5 w-5 animate-spin text-comms-cyan" />
            </div>
        </>
    );
}
