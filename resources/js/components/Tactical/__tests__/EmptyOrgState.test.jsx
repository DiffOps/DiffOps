import { describe, it, expect } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { EmptyOrgState } from '../EmptyOrgState';

describe('EmptyOrgState', () => {
    afterEach(() => cleanup());

    it('renders the title and message', () => {
        render(<EmptyOrgState title="Nenhuma organização ativa" message="Vincule uma organização." />);

        expect(screen.getByText('Nenhuma organização ativa')).toBeInTheDocument();
        expect(screen.getByText('Vincule uma organização.')).toBeInTheDocument();
    });

    it('renders only the title when message is omitted', () => {
        render(<EmptyOrgState title="Sem organização" />);

        expect(screen.getByText('Sem organização')).toBeInTheDocument();
        expect(screen.queryByText('Vincule uma organização.')).not.toBeInTheDocument();
    });
});
