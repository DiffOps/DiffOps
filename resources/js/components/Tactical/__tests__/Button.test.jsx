import { describe, it, expect, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';
import { Button } from '../Button';

describe('Button', () => {
    afterEach(() => cleanup());

    it('renders with correct variant classes', () => {
        render(<Button variant="primary">Primary</Button>);
        const btn = screen.getByRole('button', { name: 'Primary' });
        expect(btn).toHaveClass('bg-nv-green');
        expect(btn).toHaveClass('text-obsidian');
    });

    it('renders all variants', () => {
        const variants = ['primary', 'danger', 'ghost', 'metallic'];
        variants.forEach((variant) => {
            render(<Button variant={variant}>Test</Button>);
            expect(screen.getByRole('button', { name: 'Test' })).toBeInTheDocument();
            cleanup();
        });
    });

    it('shows loading state when loading', () => {
        render(<Button loading>Loading</Button>);
        const btn = screen.getByRole('button');
        expect(btn).toBeDisabled();
        expect(btn.querySelector('svg')).toHaveClass('animate-spin');
    });

    it('calls onClick when not disabled', () => {
        const handleClick = vi.fn();
        render(<Button onClick={handleClick}>Click me</Button>);
        fireEvent.click(screen.getByRole('button'));
        expect(handleClick).toHaveBeenCalledTimes(1);
    });

    it('does not call onClick when disabled', () => {
        const handleClick = vi.fn();
        render(<Button disabled onClick={handleClick}>Disabled</Button>);
        fireEvent.click(screen.getByRole('button'));
        expect(handleClick).not.toHaveBeenCalled();
    });

    it('applies size classes', () => {
        const sizes = ['sm', 'md', 'lg'];
        sizes.forEach((size) => {
            render(<Button size={size}>Size</Button>);
            expect(screen.getByRole('button', { name: 'Size' })).toBeInTheDocument();
            cleanup();
        });
    });
});