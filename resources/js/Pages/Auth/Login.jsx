import { useState } from 'react';
import { usePage, Head } from '@inertiajs/react';
import { Link, router } from '@inertiajs/react';
import { Shield, Github, Lock, Mail, AlertCircle, Loader2, CheckCircle, Eye, EyeOff } from 'lucide-react';
import axios from 'axios';
import { Card, Button, Badge } from '@/components/Tactical';
import { supabase } from '@/lib/supabase';

export default function Login() {
    const { errors } = usePage().props;
    const [showPassword, setShowPassword] = useState(false);
    const [oauthLoading, setOauthLoading] = useState(false);
    const [formErrors, setFormErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const { data, setData } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    async function bridgeAndEnter(token) {
        await axios.post('/api/auth/session', { token });
        router.replace('/dashboard');
    }

    const handleOAuth = async () => {
        setOauthLoading(true);

        if (!supabase) {
            setFormErrors({ general: 'Autenticação não configurada neste ambiente.' });
            setOauthLoading(false);
            return;
        }

        const { error } = await supabase.auth.signInWithOAuth({
            provider: 'github',
            options: { redirectTo: `${window.location.origin}/auth/callback` },
        });

        if (error) {
            setFormErrors({ general: error.message });
            setOauthLoading(false);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setProcessing(true);
        setFormErrors({});

        if (!supabase) {
            setFormErrors({ general: 'Autenticação não configurada neste ambiente.' });
            setProcessing(false);
            return;
        }

        const { data: sessionData, error } = await supabase.auth.signInWithPassword({
            email: data.email,
            password: data.password,
        });

        if (error || !sessionData?.session) {
            setFormErrors({ general: error?.message ?? 'Falha no login' });
            setProcessing(false);
            return;
        }

        try {
            await bridgeAndEnter(sessionData.session.access_token);
        } catch {
            setFormErrors({ general: 'Não foi possível iniciar a sessão.' });
            setProcessing(false);
        }
    };

    return (
        <>
            <Head title="Login - DiffOps" />
            <div className="min-h-screen flex items-center justify-center bg-obsidian px-4">
                <div className="w-full max-w-md">
                    {/* Logo */}
                    <div className="text-center mb-8">
                        <Link href="/" className="inline-flex items-center justify-center gap-3 mb-6">
                            <Shield className="h-10 w-10 text-nv-green" />
                            <span className="font-mono text-2xl font-bold text-bone">DiffOps</span>
                        </Link>
                        <p className="text-dusk font-mono text-sm">Centro de Comando Tático</p>
                    </div>

                    {/* OAuth Button */}
                    <Button
                        variant="ghost"
                        className="w-full mb-6"
                        leftIcon={<Github />}
                        onClick={handleOAuth}
                        disabled={oauthLoading || processing}
                    >
                        {oauthLoading ? (
                            <>
                                <Loader2 className="h-4 w-4 animate-spin" />
                                Redirecionando para GitHub...
                            </>
                        ) : (
                            'Continuar com GitHub'
                        )}
                    </Button>

                    {/* Divider */}
                    <div className="relative my-6">
                        <div className="absolute inset-0 flex items-center">
                            <div className="w-full border-t border-graphite" />
                        </div>
                        <div className="relative flex justify-center text-sm">
                            <span className="px-2 bg-obsidian text-dusk font-mono">ou</span>
                        </div>
                    </div>

                    {/* Email/Password Form */}
                    <Card>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            {formErrors.general && (
                                <div className="flex items-center gap-2 p-3 bg-defcon-red/10 border border-defcon-red/30 rounded-lg text-defcon-red text-sm font-mono">
                                    <AlertCircle className="h-4 w-4 flex-shrink-0" />
                                    {formErrors.general}
                                </div>
                            )}

                            <div>
                                <label htmlFor="email" className="block text-xs font-mono text-dusk mb-1">Email</label>
                                <div className="relative">
                                    <Mail className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-barrel" />
                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="w-full pl-10 pr-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone placeholder-dusk focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                                        placeholder="seu@email.com"
                                        required
                                        autoComplete="email"
                                    />
                                </div>
                                {formErrors.email && <p className="mt-1 text-sm text-defcon-red">{formErrors.email}</p>}
                            </div>

                            <div>
                                <label htmlFor="password" className="block text-xs font-mono text-dusk mb-1">Senha</label>
                                <div className="relative">
                                    <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-barrel" />
                                    <input
                                        type={showPassword ? 'text' : 'password'}
                                        id="password"
                                        name="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        className="w-full pl-10 pr-10 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone placeholder-dusk focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                                        placeholder="••••••••"
                                        required
                                        autoComplete="current-password"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-barrel hover:text-bone"
                                    >
                                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                </div>
                                {formErrors.password && <p className="mt-1 text-sm text-defcon-red">{formErrors.password}</p>}
                            </div>

                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="remember"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        className="w-4 h-4 bg-obsidian border-graphite rounded text-nv-green focus:ring-nv-green"
                                    />
                                    <span className="text-sm font-mono text-dusk">Lembrar-me</span>
                                </label>
                                <Link href="#" className="text-xs font-mono text-comms-cyan hover:text-comms-cyan/80">
                                    Esqueci a senha
                                </Link>
                            </div>

                            <Button type="submit" className="w-full" loading={processing} leftIcon={<Lock />}>
                                {processing ? 'Entrando...' : 'Entrar'}
                            </Button>
                        </form>

                        <div className="mt-6 pt-6 border-t border-graphite text-center">
                            <p className="text-sm font-mono text-dusk">
                                Não tem conta?{' '}
                                <Link href="/register" className="text-comms-cyan hover:text-comms-cyan/80 font-medium">
                                    Registrar
                                </Link>
                            </p>
                        </div>
                    </Card>

                    {/* Features */}
                    <div className="grid grid-cols-3 gap-4 mt-6 text-center">
                        <div className="p-4 bg-plate border border-graphite rounded-lg">
                            <Shield className="h-8 w-8 mx-auto mb-2 text-nv-green" />
                            <p className="font-mono text-xs text-bone">Auth Seguro</p>
                            <p className="text-[10px] text-dusk">JWT stateless via Supabase</p>
                        </div>
                        <div className="p-4 bg-plate border border-graphite rounded-lg">
                            <Github className="h-8 w-8 mx-auto mb-2 text-comms-cyan" />
                            <p className="font-mono text-xs text-bone">OAuth GitHub</p>
                            <p className="text-[10px] text-dusk">Login único integrado</p>
                        </div>
                        <div className="p-4 bg-plate border border-graphite rounded-lg">
                            <Lock className="h-8 w-8 mx-auto mb-2 text-amber" />
                            <p className="font-mono text-xs text-bone">RBAC</p>
                            <p className="text-[10px] text-dusk">Commander / Operator</p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}