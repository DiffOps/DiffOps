import { describe, it, expect } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { StatusPill } from '../StatusPill';

describe('StatusPill', () => {
    afterEach(() => cleanup());

    it('renders scanning status', () => {
        render(<StatusPill status="scanning" />);
        expect(screen.getByText('SCANNING')).toBeInTheDocument();
    });

    it('renders completed status', () => {
        render(<StatusPill status="completed" />);
        expect(screen.getByText('COMPLETED')).toBeInTheDocument();
    });

    it('renders failed status', () => {
        render(<StatusPill status="failed" />);
        expect(screen.getByText('FAILED')).toBeInTheDocument();
    });

    it('renders idle status', () => {
        render(<StatusPill status="idle" />);
        expect(screen.getByText('IDLE')).toBeInTheDocument();
    });

    it('uses custom label when provided', () => {
        render(<StatusPill status="scanning" label="CUSTOM" />);
        expect(screen.getByText('CUSTOM')).toBeInTheDocument();
        expect(screen.queryByText('SCANNING')).not.toBeInTheDocument();
    });
});