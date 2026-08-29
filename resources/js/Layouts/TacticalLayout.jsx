import { ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, GitBranch, FileText, Activity, Settings, Eye, LogOut, Menu, X, Shield, BarChart2 } from 'lucide-react';
import { useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { cn } from '@/lib/utils';
import { supabase } from '@/lib/supabase';
import { OrgSwitcher } from '@/components/Tactical';

const navigation = [
    { name: 'Dashboard', href: '/', icon: LayoutDashboard },
    { name: 'Incursões', href: '/incursions', icon: GitBranch },
    { name: 'Repositórios', href: '/repos', icon: FileText },
    { name: 'Combat History', href: '/operations-log', icon: Activity },
    { name: 'Briefing', href: '/briefing', icon: BarChart2 },
    { name: 'Watchlist', href: '/watchlist', icon: Eye },
    { name: 'Settings', href: '/settings', icon: Settings },
];

export function TacticalLayout({ children }: { children: ReactNode }) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const { auth, organizations = [], currentOrganization = null } = usePage().props;

    const handleSwitchOrg = (id) => {
        router.post('/org/switch', { organization_id: id }, {
            onSuccess: () => router.reload(),
        });
    };

    return (
        <div className="min-h-screen bg-obsidian text-bone font-sans flex">
            {/* Mobile sidebar backdrop */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 bg-obsidian/80 z-40 lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                    aria-hidden="true"
                />
            )}

            {/* Sidebar */}
            <aside
                className={cn(
                    'fixed lg:static inset-y-0 left-0 z-50 w-64 bg-asphalt border-r border-graphite',
                    'transform transition-transform duration-300 ease-in-out',
                    'flex flex-col',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
                )}
            >
                <div className="flex flex-col h-full">
                    {/* Logo / Header */}
                    <div className="flex items-center justify-between h-16 px-4 border-b border-graphite">
                        <Link href="/" className="flex items-center gap-2">
                            <Shield className="h-6 w-6 text-nv-green" />
                            <span className="font-mono text-xl font-bold text-bone">DiffOps</span>
                        </Link>
                        <button
                            className="lg:hidden p-2 rounded-md text-dusk hover:text-bone hover:bg-plate"
                            onClick={() => setSidebarOpen(false)}
                            aria-label="Close sidebar"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    {/* Navigation */}
                    <nav className="flex-1 p-4 space-y-1 overflow-y-auto" aria-label="Main navigation">
                        {navigation.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={cn(
                                    'flex items-center gap-3 px-3 py-2.5 text-sm font-mono rounded-lg border transition-colors',
                                    'text-dusk border-transparent hover:text-bone hover:bg-plate hover:border-graphite',
                                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-comms-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-asphalt'
                                )}
                                onClick={() => setSidebarOpen(false)}
                            >
                                <item.icon className="h-5 w-5 flex-shrink-0" aria-hidden="true" />
                                {item.name}
                            </Link>
                        ))}
                    </nav>

                    {/* User section */}
                    <div className="p-4 border-t border-graphite space-y-3">
                        {auth?.user ? (
                            <div className="flex items-center gap-3">
                                {auth.user.avatar_url && (
                                    <img
                                        src={auth.user.avatar_url}
                                        alt=""
                                        className="h-8 w-8 rounded-full bg-plate"
                                    />
                                )}
                                <div className="flex-1 min-w-0">
                                    <p className="text-xs font-mono text-dusk truncate">{auth.user.username}</p>
                                    <p className="text-[10px] font-mono text-barrel truncate">{auth.user.email}</p>
                                </div>
                            </div>
                        ) : null}
                        {auth?.user && (
                            <OrgSwitcher
                                organizations={organizations}
                                currentOrganization={currentOrganization}
                                onSwitch={handleSwitchOrg}
                            />
                        )}
                        {auth?.user && (
                            <button
                                type="button"
                                className="mt-2 flex items-center justify-center gap-2 w-full px-3 py-2 text-xs font-mono text-defcon-red border border-defcon-red/30 rounded-lg hover:bg-defcon-red/10 transition-colors"
                                onClick={async () => {
                                    await supabase?.auth.signOut();
                                    await axios.delete('/api/auth/session');
                                    router.visit('/login');
                                }}
                            >
                                <LogOut className="h-4 w-4" />
                                Logout
                            </button>
                        )}
                        {!auth?.user ? (
                            <Link
                                href="/login"
                                className="flex items-center justify-center gap-2 w-full px-3 py-2 text-sm font-mono text-nv-green border border-nv-green/30 rounded-lg hover:bg-nv-green/10 transition-colors"
                            >
                                <LogOut className="h-4 w-4" />
                                Login
                            </Link>
                        )}
                    </div>
                </div>
            </aside>

            {/* Main content */}
            <main className="flex-1 lg:ml-0 min-w-0 flex flex-col">
                {/* Top bar */}
                <header className="sticky top-0 z-30 h-16 bg-plate/80 backdrop-blur-sm border-b border-graphite flex items-center justify-between px-4 lg:px-6">
                    <div className="flex items-center gap-4">
                        <button
                            className="lg:hidden p-2 rounded-md text-dusk hover:text-bone hover:bg-steel"
                            onClick={() => setSidebarOpen(true)}
                            aria-label="Open sidebar"
                        >
                            <Menu className="h-5 w-5" />
                        </button>
                        <div className="hidden sm:block">
                            <nav className="flex items-center gap-1" aria-label="Breadcrumb">
                                <Link href="/" className="text-dusk hover:text-bone text-sm font-mono">DiffOps</Link>
                                <span className="text-barrel mx-1">/</span>
                                <span className="text-bone font-mono text-sm capitalize">{usePage().component.replace(/([A-Z])/g, ' $1').trim()}</span>
                            </nav>
                        </div>
                    </div>

                    {/* Status indicators */}
                    <div className="flex items-center gap-3">
                        <div className="hidden md:flex items-center gap-2 px-2 py-1 rounded-full bg-obsidian border border-graphite">
                            <span className="relative flex h-2 w-2">
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-nv-green opacity-75" />
                                <span className="relative inline-flex rounded-full h-2 w-2 bg-nv-green" />
                            </span>
                            <span className="text-xs font-mono text-nv-green">LIVE</span>
                        </div>
                        <div className="text-right hidden sm:block">
                            <div className="text-xs font-mono text-dusk">UTC</div>
                            <div className="text-sm font-mono text-bone tabular-nums" id="utc-clock">00:00:00</div>
                        </div>
                    </div>
                </header>

                {/* Page content */}
                <div className="flex-1 p-4 lg:p-6 overflow-auto">
                    {children}
                </div>
            </main>
        </div>
    );
}