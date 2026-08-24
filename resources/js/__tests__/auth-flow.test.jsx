import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';
import { router } from '@inertiajs/react';
import Callback from '@/Pages/Auth/Callback';
import { supabase } from '@/lib/supabase';

vi.mock('@/lib/supabase', () => ({
    supabase: { auth: { signOut: vi.fn(async () => {}) } },
    exchangeAndBridgeSession: vi.fn(),
}));

import { exchangeAndBridgeSession } from '@/lib/supabase';

describe('Auth Callback', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        cleanup();
        router.replace.mockClear();
    });

    it('bridges the session and goes to the dashboard', async () => {
        exchangeAndBridgeSession.mockResolvedValue(true);

        render(<Callback />);

        expect(screen.getByText(/autenticando/i)).toBeInTheDocument();

        await waitFor(() => {
            expect(router.replace).toHaveBeenCalledWith('/dashboard');
        });
    });

    it('goes back to login without a session', async () => {
        exchangeAndBridgeSession.mockResolvedValue(false);

        render(<Callback />);

        await waitFor(() => {
            expect(router.replace).toHaveBeenCalledWith('/login');
        });
    });

    it('signs out through the shared client', async () => {
        await supabase.auth.signOut();
        expect(supabase.auth.signOut).toHaveBeenCalledTimes(1);
    });
});
