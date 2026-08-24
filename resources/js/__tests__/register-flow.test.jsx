import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useRouter } from '@inertiajs/react';
import Register from '@/Pages/Auth/Register';
import { registerWithEmail } from '@/lib/supabase';

vi.mock('@/lib/supabase', () => ({
    supabase: null,
    exchangeAndBridgeSession: vi.fn(async () => false),
    registerWithEmail: vi.fn(),
}));

function fillForm(user) {
    return user.type(screen.getByLabelText(/nome completo/i), 'Carlos Goulart')
        .then(() => user.type(screen.getByLabelText(/^email/i), 'carlos@diffops.test'))
        .then(() => user.type(screen.getByLabelText(/^senha/i), 'secret123'))
        .then(() => user.type(screen.getByLabelText(/confirmar senha/i), 'secret123'));
}

describe('Register page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        useRouter().replace.mockClear();
    });

    it('renders the signup form', () => {
        render(<Register />);

        expect(screen.getByRole('button', { name: /criar conta/i })).toBeInTheDocument();
        expect(screen.getByText(/já tem conta/i)).toBeInTheDocument();
    });

    it('bridges the session and enters the dashboard on immediate session', async () => {
        const user = userEvent.setup();
        registerWithEmail.mockResolvedValue({ needsConfirmation: false });

        render(<Register />);
        await fillForm(user);
        await user.click(screen.getByRole('button', { name: /criar conta/i }));

        await waitFor(() => {
            expect(registerWithEmail).toHaveBeenCalledWith('carlos@diffops.test', 'secret123', 'Carlos Goulart');
            expect(useRouter().replace).toHaveBeenCalledWith('/dashboard');
        });
    });

    it('shows the confirmation state when email confirmation is required', async () => {
        const user = userEvent.setup();
        registerWithEmail.mockResolvedValue({ needsConfirmation: true });

        render(<Register />);
        await fillForm(user);
        await user.click(screen.getByRole('button', { name: /criar conta/i }));

        expect(await screen.findByText(/confirme seu email/i)).toBeInTheDocument();
    });

    it('surfaces provider errors', async () => {
        const user = userEvent.setup();
        registerWithEmail.mockRejectedValue(new Error('User already registered'));

        render(<Register />);
        await fillForm(user);
        await user.click(screen.getByRole('button', { name: /criar conta/i }));

        expect(await screen.findByText(/user already registered/i)).toBeInTheDocument();
    });

    it('blocks submit when passwords do not match', async () => {
        const user = userEvent.setup();

        render(<Register />);
        await user.type(screen.getByLabelText(/nome completo/i), 'Carlos');
        await user.type(screen.getByLabelText(/^email/i), 'carlos@diffops.test');
        await user.type(screen.getByLabelText(/^senha/i), 'secret123');
        await user.type(screen.getByLabelText(/confirmar senha/i), 'different');
        await user.click(screen.getByRole('button', { name: /criar conta/i }));

        expect(await screen.findByText(/senhas não coincidem/i)).toBeInTheDocument();
        expect(registerWithEmail).not.toHaveBeenCalled();
    });
});
