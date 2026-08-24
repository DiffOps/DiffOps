import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import Login from '@/Pages/Auth/Login';

// Render real da página de login: pega crashes que só acontecem no render
// (ex.: hook usado sem import), que o contrato de import eager não cobre.
describe('Login page', () => {
    it('renders the oauth and email form', () => {
        render(<Login />);

        expect(screen.getByText(/continuar com github/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/senha/i)).toBeInTheDocument();
    });
});
