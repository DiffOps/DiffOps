import { describe, it, expect } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { VerdictBadge } from '../VerdictBadge';

describe('VerdictBadge', () => {
    afterEach(() => cleanup());

    it('renders CLEAR verdict with green colors', () => {
        render(<VerdictBadge verdict="clear" />);
        const badge = screen.getByText('CLEAR');
        expect(badge).toHaveClass('bg-nv-green/10');
        expect(badge).toHaveClass('text-nv-green');
        expect(badge).toHaveClass('border-nv-green/30');
    });

    it('renders FLAGGED verdict with amber colors', () => {
        render(<VerdictBadge verdict="flagged" />);
        const badge = screen.getByText('FLAGGED');
        expect(badge).toHaveClass('bg-amber/10');
        expect(badge).toHaveClass('text-amber');
        expect(badge).toHaveClass('border-amber/30');
    });

    it('renders HOSTILE verdict with red colors', () => {
        render(<VerdictBadge verdict="hostile" />);
        const badge = screen.getByText('HOSTILE');
        expect(badge).toHaveClass('bg-defcon-red/10');
        expect(badge).toHaveClass('text-defcon-red');
        expect(badge).toHaveClass('border-defcon-red/30');
    });

    it('applies size classes', () => {
        const sizes = ['sm', 'md', 'lg'];
        sizes.forEach((size) => {
            render(<VerdictBadge verdict="clear" size={size} />);
            expect(screen.getByText('CLEAR')).toBeInTheDocument();
            cleanup();
        });
    });
});