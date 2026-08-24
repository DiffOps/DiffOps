import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Shield, UserPlus, AlertCircle, Loader2, MailCheck, Eye, EyeOff } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { registerWithEmail } from '@/lib/supabase';

export default function Register() {
    const [showPassword, setShowPassword] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [formErrors, setFormErrors] = useState({});
    const [needsConfirmation, setNeedsConfirmation] = useState(false);
    const [data, setData] = useState({ full_name: '', email: '', password: '', password_confirmation: '' });

    const set = (key) => (e) =>
        setData((prev) => ({ ...prev, [key]: e.target.value }));

    async function handleSubmit(e) {
        e.preventDefault();
        setFormErrors({});

        if (data.password !== data.password_confirmation) {
            setFormErrors({ general: 'As senhas não coincidem.' });
            return;
        }

        setProcessing(true);

        try {
            const result = await registerWithEmail(data.email, data.password, data.full_name);

            if (result.needsConfirmation) {
                setNeedsConfirmation(true);
            } else {
                router.replace('/dashboard');
            }
        } catch (err) {
            setFormErrors({ general: err instanceof Error ? err.message : 'Falha no registro.' });
        } finally {
            setProcessing(false);
        }
    }

    if (needsConfirmation) {
        return (
            <>
                <Head title="Confirme seu email — DiffOps" />
                <div className="min-h-screen flex flex-col items-center justify-center gap-4 bg-obsidian px-4 text-center">
                    <MailCheck className="h-12 w-12 text-nv-green" />
                    <h1 className="font-mono text-xl font-bold text-bone">Confirme seu email</h1>
                    <p className="max-w-sm font-mono text-sm text-dusk">
                        Enviamos um link de confirmação para <span className="text-bone">{data.email}</span>.
                        Depois de confirmar, faça login normalmente.
                    </p>
                    <Link href="/login" className="mt-2 font-mono text-sm text-comms-cyan hover:text-comms-cyan/80">
                        Voltar ao login
                    </Link>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Registrar — DiffOps" />
            <div className="min-h-screen bg-obsidian px-4 py-10">
                <div className="w-full max-w-md mx-auto">
                    <div className="text-center mb-8">
                        <Link href="/" className="inline-flex items-center justify-center gap-3 mb-2">
                            <Shield className="h-10 w-10 text-nv-green" />
                            <span className="font-mono text-2xl font-bold text-bone">DiffOps</span>
                        </Link>
                        <p className="text-dusk font-mono text-sm">Crie sua conta de comando</p>
                    </div>

                    <div className="bg-plate border border-graphite rounded-lg p-6">
                        <form onSubmit={handleSubmit} className="space-y-4">
                            {formErrors.general && (
                                <div className="flex items-center gap-2 p-3 bg-defcon-red/10 border border-defcon-red/30 rounded-lg text-defcon-red text-sm font-mono">
                                    <AlertCircle className="h-4 w-4 flex-shrink-0" />
                                    {formErrors.general}
                                </div>
                            )}

                            <div>
                                <label htmlFor="full_name" className="block text-xs font-mono text-dusk mb-1">Nome completo</label>
                                <input
                                    type="text"
                                    id="full_name"
                                    value={data.full_name}
                                    onChange={set('full_name')}
                                    required
                                    autoComplete="name"
                                    className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone placeholder-barrel focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                                />
                            </div>

                            <div>
                                <label htmlFor="email" className="block text-xs font-mono text-dusk mb-1">Email</label>
                                <input
                                    type="email"
                                    id="email"
                                    value={data.email}
                                    onChange={set('email')}
                                    required
                                    autoComplete="email"
                                    className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone placeholder-barrel focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                                />
                            </div>

                            <div>
                                <label htmlFor="password" className="block text-xs font-mono text-dusk mb-1">Senha</label>
                                <div className="relative">
                                    <input
                                        type={showPassword ? 'text' : 'password'}
                                        id="password"
                                        value={data.password}
                                        onChange={set('password')}
                                        required
                                        minLength={6}
                                        autoComplete="new-password"
                                        className="w-full px-3 py-2 pr-10 bg-obsidian border border-graphite rounded-lg font-mono text-bone focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                                    />
                                    <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute right-3 top-1/2 -translate-y-1/2 text-barrel hover:text-bone">
                                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label htmlFor="password_confirmation" className="block text-xs font-mono text-dusk mb-1">Confirmar senha</label>
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    id="password_confirmation"
                                    value={data.password_confirmation}
                                    onChange={set('password_confirmation')}
                                    required
                                    minLength={6}
                                    autoComplete="new-password"
                                    className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-nv-green text-obsidian font-mono font-medium rounded-lg hover:bg-nv-green/90 disabled:opacity-50 transition-colors"
                            >
                                {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <UserPlus className="h-4 w-4" />}
                                {processing ? 'Criando...' : 'Criar conta'}
                            </button>
                        </form>

                        <div className="mt-6 pt-6 border-t border-graphite text-center">
                            <p className="text-sm font-mono text-dusk">
                                Já tem conta?{' '}
                                <Link href="/login" className="text-comms-cyan hover:text-comms-cyan/80 font-medium">
                                    Entrar
                                </Link>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
