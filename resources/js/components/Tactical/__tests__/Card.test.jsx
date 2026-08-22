import { describe, it, expect } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { Card } from '../Card';

describe('Card', () => {
    afterEach(() => cleanup());

    it('renders children', () => {
        render(<Card>Card content</Card>);
        expect(screen.getByText('Card content')).toBeInTheDocument();
    });

    it('applies variant classes', () => {
        const variants = ['default', 'elevated', 'outlined'];
        variants.forEach((variant) => {
            render(<Card variant={variant}>Test</Card>);
            expect(screen.getByText('Test')).toBeInTheDocument();
            cleanup();
        });
    });

    it('applies corners by default', () => {
        render(<Card>Corners</Card>);
        expect(screen.getByText('Corners').parentElement).toHaveClass('before:absolute');
    });

    it('removes corners when corners={false}', () => {
        render(<Card corners={false}>No corners</Card>);
        expect(screen.getByText('No corners').parentElement).not.toHaveClass('before:absolute');
    });
});