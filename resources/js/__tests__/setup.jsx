import '@testing-library/jest-dom';
import { vi } from 'vitest';

const routerMock = {
    replace: vi.fn(),
    visit: vi.fn(),
    get: vi.fn(),
    post: vi.fn(),
    reload: vi.fn(),
};

// Props compartilhadas das Pages: testes podem sobrescrever por página antes
// do render (window.__pageProps = { Dashboard: {...} }) — o mock faz o merge.
const basePageProps = {
    auth: { user: null },
    flash: {},
    errors: {},
};

globalThis.__pageProps = {};

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            ...basePageProps,
            ...(globalThis.__pageProps || {}),
        },
        url: '/',
        component: 'Dashboard',
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

vi.mock('react-window', () => ({
    // Render-prop síncrona: devolve as linhas visíveis sem virtualizar,
    // suficiente para smoke tests em happy-dom (evita string refs).
    FixedSizeList: ({ itemCount, children }) => (
        <div>
            {Array.from({ length: itemCount ?? 0 }, (_, index) =>
                children({ index, style: {} }),
            )}
        </div>
    ),
}));

export { routerMock };

// supabase-js ≥2.14 importa realtime e lança em Node sem WebSocket nativo;
// nenhum teste cria client de verdade — mock evita o import do pacote real.
vi.mock('@supabase/supabase-js', () => ({
    createClient: vi.fn(() => null),
}));

vi.mock('zustand', () => ({
    create: (fn) => {
        let state = fn(set => ({ set }));
        const set = (partial) => {
            state = typeof partial === 'function' ? partial(state) : { ...state, ...partial };
        };
        const get = () => state;
        return Object.assign(() => state, { getState: get, setState: set, subscribe: vi.fn() });
    },
}));

global.ResizeObserver = vi.fn().mockImplementation(() => ({
    observe: vi.fn(),
    unobserve: vi.fn(),
    disconnect: vi.fn(),
}));

global.IntersectionObserver = vi.fn().mockImplementation(() => ({
    observe: vi.fn(),
    unobserve: vi.fn(),
    disconnect: vi.fn(),
}));

Object.defineProperty(HTMLCanvasElement.prototype, 'getContext', {
    value: vi.fn(() => ({
        fillRect: vi.fn(),
        clearRect: vi.fn(),
        getImageData: vi.fn(() => ({ data: [] })),
        putImageData: vi.fn(),
        createImageData: vi.fn(),
        setTransform: vi.fn(),
        drawImage: vi.fn(),
        save: vi.fn(),
        fillText: vi.fn(),
        restore: vi.fn(),
        beginPath: vi.fn(),
        moveTo: vi.fn(),
        lineTo: vi.fn(),
        closePath: vi.fn(),
        stroke: vi.fn(),
        translate: vi.fn(),
        scale: vi.fn(),
        rotate: vi.fn(),
        arc: vi.fn(),
        fill: vi.fn(),
        measureText: vi.fn(() => ({ width: 0 })),
        transform: vi.fn(),
        rect: vi.fn(),
        clip: vi.fn(),
    })),
    writable: true,
});

HTMLCanvasElement.prototype.toDataURL = vi.fn(() => 'data:image/png;base64,');