import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const mocks = vi.hoisted(() => ({
    linkGitHubIdentity: vi.fn(),
    exchangeAndBridgeSession: vi.fn(),
    supabaseValue: { current: null },
    pageProps: { current: {} },
}));

vi.mock('@/lib/supabase', () => ({
    get supabase() {
        return mocks.supabaseValue.current;
    },
    linkGitHubIdentity: mocks.linkGitHubIdentity,
    exchangeAndBridgeSession: mocks.exchangeAndBridgeSession,
}));

const { routerMock } = vi.hoisted(() => ({
    routerMock: {
        replace: vi.fn(),
        reload: vi.fn(),
        visit: vi.fn(),
        get: vi.fn(),
        post: vi.fn(),
    },
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: mocks.pageProps.current,
        url: '/settings',
        component: 'Settings/Index',
        version: '1',
    }),
    useRouter: () => routerMock,
    router: routerMock,
    useForm: (initial = {}) => ({
        data: typeof initial === 'function' ? initial() : initial,
        setData: vi.fn(),
        post: vi.fn(),
        get: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
        processing: false,
        wasSuccessful: false,
        recentlySuccessful: false,
        errors: {},
        hasErrors: false,
        reset: vi.fn(),
        setError: vi.fn(),
        clearErrors: vi.fn(),
        transform: vi.fn(),
    }),
    Head: ({ children }) => children,
    Link: ({ children, href, ...props }) => (
        <a href={href} {...props}>{children}</a>
    ),
}));

import SettingsIndex from '@/Pages/Settings/Index';

let locationMock;

function baseProps(overrides = {}) {
    return {
        user: {
            id: 'u1',
            username: 'carlos',
            email: 'carlos@diffops.test',
            avatar_url: null,
            role: 'operator',
        },
        preferences: {
            theme: 'tactical',
            notifications: { email: false, push: false, realtime: true },
            language: 'pt-BR',
            timezone: 'UTC',
        },
        github: { linked: false, username: null, avatar_url: null },
        flash: {},
        errors: {},
        ...overrides,
    };
}

describe('Settings GitHub link', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        cleanup();
        sessionStorage.clear();

        mocks.supabaseValue.current = { auth: { linkIdentity: vi.fn() } };
        mocks.linkGitHubIdentity.mockReset();
        mocks.exchangeAndBridgeSession.mockReset();

        locationMock = {
            origin: 'http://localhost',
            href: '',
            assign: vi.fn(),
            replace: vi.fn(),
            pathname: '/settings',
            search: '',
            hash: '',
        };
        Object.defineProperty(window, 'location', {
            value: locationMock,
            configurable: true,
            writable: true,
        });
    });

    it('renders the not-linked state with a connect button', () => {
        mocks.pageProps.current = baseProps();

        render(<SettingsIndex />);

        expect(screen.getByText(/nenhuma conta github vinculada/i)).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /vincular github/i }),
        ).toBeInTheDocument();
    });

    it('renders the linked state without a connect button', () => {
        mocks.pageProps.current = baseProps({
            github: {
                linked: true,
                username: 'octocat',
                avatar_url: 'http://x/octocat.png',
            },
        });

        render(<SettingsIndex />);

        const avatar = screen.getByAltText('octocat');
        expect(avatar).toHaveAttribute('src', 'http://x/octocat.png');
        expect(screen.getByText(/@octocat/i)).toBeInTheDocument();
        expect(screen.getByText(/vinculado/i)).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /vincular github/i }),
        ).not.toBeInTheDocument();
    });

    it('links the github account and navigates on click', async () => {
        const user = userEvent.setup();
        mocks.pageProps.current = baseProps();
        mocks.linkGitHubIdentity.mockResolvedValue('https://oauth.example/authorize');

        render(<SettingsIndex />);
        await user.click(screen.getByRole('button', { name: /vincular github/i }));

        await waitFor(() => {
            expect(mocks.linkGitHubIdentity).toHaveBeenCalledWith('http://localhost/settings');
            expect(locationMock.assign).toHaveBeenCalledWith('https://oauth.example/authorize');
        });
        expect(sessionStorage.getItem('diffops.github.link.pending')).toBe('1');
    });

    it('surfaces link errors', async () => {
        const user = userEvent.setup();
        mocks.pageProps.current = baseProps();
        mocks.linkGitHubIdentity.mockRejectedValue(new Error('boom'));

        render(<SettingsIndex />);
        await user.click(screen.getByRole('button', { name: /vincular github/i }));

        expect(
            await screen.findByText(/falha ao vincular conta github/i),
        ).toBeInTheDocument();
    });

    it('re-bridges the session after oauth return', async () => {
        mocks.pageProps.current = baseProps();
        mocks.exchangeAndBridgeSession.mockResolvedValue(true);
        sessionStorage.setItem('diffops.github.link.pending', '1');

        render(<SettingsIndex />);

        await waitFor(() => {
            expect(mocks.exchangeAndBridgeSession).toHaveBeenCalledTimes(1);
            expect(routerMock.reload).toHaveBeenCalledTimes(1);
        });
        expect(sessionStorage.getItem('diffops.github.link.pending')).toBeNull();
    });

    it('shows the not-configured state when supabase is null', () => {
        mocks.supabaseValue.current = null;
        mocks.pageProps.current = baseProps();

        render(<SettingsIndex />);

        expect(
            screen.getByText(/autenticação não configurada neste ambiente/i),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /vincular github/i }),
        ).toBeDisabled();
    });
});
