import { describe, it, expect } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { Pill } from '../Pill';

describe('Pill', () => {
    afterEach(() => cleanup());

    it('renders with correct variant classes', () => {
        render(<Pill variant="nv-green">NV Green</Pill>);
        const pill = screen.getByText('NV Green');
        expect(pill).toHaveClass('bg-nv-green/10');
        expect(pill).toHaveClass('text-nv-green');
        expect(pill).toHaveClass('border-nv-green/30');
    });

    it('renders all variants', () => {
        const variants = ['nv-green', 'amber', 'defcon-red', 'comms-cyan', 'dusk'];
        variants.forEach((variant) => {
            render(<Pill variant={variant}>Test</Pill>);
            expect(screen.getByText('Test')).toBeInTheDocument();
            cleanup();
        });
    });

    it('applies custom className', () => {
        render(<Pill className="custom-pill">Test</Pill>);
        expect(screen.getByText('Test')).toHaveClass('custom-pill');
    });
});