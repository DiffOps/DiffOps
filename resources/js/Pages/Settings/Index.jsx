import { useState, useEffect } from 'react';
import { usePage, Head, Link, useForm, router } from '@inertiajs/react';
import { User, Mail, Bell, Shield, Globe, Save, Loader2, CheckCircle, Github } from 'lucide-react';
import { Card, Button, Pill, Badge } from '@/components/Tactical';
import { supabase, linkGitHubIdentity, exchangeAndBridgeSession } from '@/lib/supabase';

export default function SettingsIndex() {
    const { user, preferences, github: githubProp } = usePage().props;
    const github = githubProp ?? { linked: false, username: null, avatar_url: null };

    const [linkError, setLinkError] = useState(false);

    useEffect(() => {
        if (sessionStorage.getItem('diffops.github.link.pending')) {
            sessionStorage.removeItem('diffops.github.link.pending');
            (async () => {
                await exchangeAndBridgeSession();
                router.reload();
            })();
        }
    }, []);

    const handleLinkGitHub = async () => {
        setLinkError(false);
        try {
            sessionStorage.setItem('diffops.github.link.pending', '1');
            const url = await linkGitHubIdentity(window.location.origin + '/settings');
            if (url) {
                window.location.assign(url);
            }
        } catch {
            setLinkError(true);
        }
    };

    const { data, setData, errors, processing, recentlySuccessful } = useForm({
        username: user.username,
        email: user.email,
        notifications: {
            email: preferences.notifications.email,
            push: preferences.notifications.push,
        },
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('settings.update'), {
            onSuccess: () => {},
            onError: () => {},
        });
    };

    const handleChange = (key, value) => {
        const keys = key.split('.');
        if (keys.length === 2) {
            setData(keys[0], { ...data[keys[0]], [keys[1]]: value });
        } else {
            setData(key, value);
        }
    };

    return (
        <>
            <Head title="Configurações" />
            <div className="max-w-3xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-mono font-bold text-bone">Configurações</h1>
                    <p className="text-dusk font-mono text-sm">Gerencie seu perfil e preferências</p>
                </div>

                {/* Profile Section */}
                <Card>
                    <div className="flex items-center gap-4 mb-6">
                        {user.avatar_url ? (
                            <img src={user.avatar_url} alt="" className="h-16 w-16 rounded-full bg-plate" />
                        ) : (
                            <div className="h-16 w-16 rounded-full bg-plate flex items-center justify-center">
                                <User className="h-8 w-8 text-barrel" />
                            </div>
                        )}
                        <div>
                            <h2 className="text-lg font-mono font-bold text-bone">{user.username}</h2>
                            <p className="text-dusk font-mono text-sm">{user.email}</p>
                            <Badge variant={user.role === 'commander' ? 'hostile' : 'flagged'} className="mt-1">
                                {user.role.toUpperCase()}
                            </Badge>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div>
                            <label htmlFor="username" className="block text-xs font-mono text-dusk mb-1">Username</label>
                            <input
                                type="text"
                                id="username"
                                value={data.username}
                                onChange={(e) => handleChange('username', e.target.value)}
                                className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone placeholder-dusk focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                            />
                            {errors.username && <p className="mt-1 text-sm text-defcon-red">{errors.username}</p>}
                        </div>
                        <div>
                            <label htmlFor="email" className="block text-xs font-mono text-dusk mb-1">Email</label>
                            <input
                                type="email"
                                id="email"
                                value={data.email}
                                onChange={(e) => handleChange('email', e.target.value)}
                                className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone placeholder-dusk focus:outline-none focus:ring-2 focus:ring-comms-cyan"
                            />
                            {errors.email && <p className="mt-1 text-sm text-defcon-red">{errors.email}</p>}
                        </div>
                    </div>
                </Card>

                {/* Linked Accounts Section */}
                <Card>
                    <h2 className="text-lg font-mono font-bold text-bone mb-4 flex items-center gap-2">
                        <Github className="h-5 w-5 text-comms-cyan" />
                        Contas vinculadas
                    </h2>
                    {github.linked ? (
                        <div className="flex items-center gap-4">
                            {github.avatar_url ? (
                                <img
                                    src={github.avatar_url}
                                    alt={github.username ?? ''}
                                    className="h-12 w-12 rounded-full bg-plate"
                                    onError={(e) => { e.currentTarget.style.display = 'none'; }}
                                />
                            ) : (
                                <div className="h-12 w-12 rounded-full bg-plate flex items-center justify-center">
                                    <Github className="h-6 w-6 text-barrel" />
                                </div>
                            )}
                            <div>
                                <p className="font-mono text-bone">@{github.username}</p>
                                <Badge variant="clear" className="mt-1">VINCULADO</Badge>
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {supabase === null ? (
                                <p className="font-mono text-sm text-dusk">
                                    Autenticação não configurada neste ambiente.
                                </p>
                            ) : (
                                <p className="font-mono text-sm text-dusk">
                                    Nenhuma conta GitHub vinculada.
                                </p>
                            )}
                            <Button
                                onClick={handleLinkGitHub}
                                disabled={supabase === null}
                                leftIcon={<Github />}
                            >
                                Vincular GitHub
                            </Button>
                        </div>
                    )}
                    {linkError && (
                        <p className="mt-3 text-sm text-defcon-red">
                            Falha ao vincular conta GitHub. Tente novamente.
                        </p>
                    )}
                </Card>

                {/* Notifications Section */}
                <Card>
                    <h2 className="text-lg font-mono font-bold text-bone mb-4 flex items-center gap-2">
                        <Bell className="h-5 w-5 text-comms-cyan" />
                        Notificações
                    </h2>
                    <div className="space-y-4">
                        <div className="flex items-center justify-between p-3 bg-asphalt rounded-lg">
                            <div className="flex items-center gap-3">
                                <Mail className="h-5 w-5 text-barrel" />
                                <div>
                                    <p className="font-mono text-bone">Email</p>
                                    <p className="text-xs font-mono text-dusk">Receber notificações por email</p>
                                </div>
                            </div>
                            <input
                                type="checkbox"
                                checked={data.notifications.email}
                                onChange={(e) => handleChange('notifications.email', e.target.checked)}
                                className="w-5 h-5 bg-obsidian border-graphite rounded text-nv-green focus:ring-nv-green"
                            />
                        </div>
                        <div className="flex items-center justify-between p-3 bg-asphalt rounded-lg">
                            <div className="flex items-center gap-3">
                                <Bell className="h-5 w-5 text-barrel" />
                                <div>
                                    <p className="font-mono text-bone">Push</p>
                                    <p className="text-xs font-mono text-dusk">Notificações push no navegador</p>
                                </div>
                            </div>
                            <input
                                type="checkbox"
                                checked={data.notifications.push}
                                onChange={(e) => handleChange('notifications.push', e.target.checked)}
                                className="w-5 h-5 bg-obsidian border-graphite rounded text-nv-green focus:ring-nv-green"
                            />
                        </div>
                        <div className="flex items-center justify-between p-3 bg-asphalt rounded-lg">
                            <div className="flex items-center gap-3">
                                <Shield className="h-5 w-5 text-barrel" />
                                <div>
                                    <p className="font-mono text-bone">Realtime</p>
                                    <p className="text-xs font-mono text-dusk">Atualizações em tempo real no dashboard</p>
                                </div>
                            </div>
                            <input
                                type="checkbox"
                                checked={true}
                                disabled
                                className="w-5 h-5 bg-obsidian border-graphite rounded text-nv-green opacity-50 cursor-not-allowed"
                            />
                        </div>
                    </div>
                </Card>

                {/* Appearance Section */}
                <Card>
                    <h2 className="text-lg font-mono font-bold text-bone mb-4 flex items-center gap-2">
                        <Globe className="h-5 w-5 text-comms-cyan" />
                        Aparência & Localização
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-mono text-dusk mb-1">Tema</label>
                            <select className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone focus:outline-none focus:ring-2 focus:ring-comms-cyan" disabled>
                                <option value="tactical">Tactical OPS (Padrão)</option>
                            </select>
                            <p className="mt-1 text-xs font-mono text-barrel">Tema escuro militar - único disponível</p>
                        </div>
                        <div>
                            <label className="block text-xs font-mono text-dusk mb-1">Idioma</label>
                            <select className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone focus:outline-none focus:ring-2 focus:ring-comms-cyan" disabled>
                                <option value="pt-BR">Português (Brasil)</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-mono text-dusk mb-1">Fuso Horário</label>
                            <select className="w-full px-3 py-2 bg-obsidian border border-graphite rounded-lg font-mono text-bone focus:outline-none focus:ring-2 focus:ring-comms-cyan" disabled>
                                <option value="UTC">UTC</option>
                            </select>
                        </div>
                    </div>
                </Card>

                {/* Save Button */}
                <div className="flex justify-end">
                    <Button type="submit" onClick={handleSubmit} loading={processing} leftIcon={<Save />}>
                        {processing ? 'Salvando...' : recentlySuccessful ? 'Salvo!' : 'Salvar Alterações'}
                    </Button>
                </div>
            </div>
        </>
    );
}