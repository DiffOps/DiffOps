import { describe, it, expect } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { Badge } from '../Badge';

describe('Badge', () => {
    afterEach(() => cleanup());

    it('renders with correct variant classes', () => {
        render(<Badge variant="clear">CLEAR</Badge>);
        const badge = screen.getByText('CLEAR');
        expect(badge).toHaveClass('bg-nv-green/10');
        expect(badge).toHaveClass('text-nv-green');
        expect(badge).toHaveClass('border-nv-green/30');
    });

    it('renders all variants', () => {
        const variants = ['clear', 'flagged', 'hostile', 'neutral', 'scanning', 'completed', 'failed'];
        variants.forEach((variant) => {
            render(<Badge variant={variant}>TEST</Badge>);
            expect(screen.getByText('TEST')).toBeInTheDocument();
            cleanup();
        });
    });

    it('applies custom className', () => {
        render(<Badge className="custom-class">TEST</Badge>);
        expect(screen.getByText('TEST')).toHaveClass('custom-class');
    });

    it('renders children correctly', () => {
        render(<Badge><span>Custom content</span></Badge>);
        expect(screen.getByText('Custom content')).toBeInTheDocument();
    });
});