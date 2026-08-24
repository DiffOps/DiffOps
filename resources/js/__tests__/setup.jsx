import '@testing-library/jest-dom';
import { vi } from 'vitest';

const routerMock = {
    replace: vi.fn(),
    visit: vi.fn(),
    get: vi.fn(),
    post: vi.fn(),
    reload: vi.fn(),
};

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            auth: { user: null },
            flash: {},
            errors: {},
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

export { routerMock };

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