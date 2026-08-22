import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { IncursionRow } from '../IncursionRow';

const mockProps = {
    id: 'inc-1',
    timestamp: '2026-08-22T10:30:00Z',
    repository: 'org/repo',
    prNumber: 42,
    author: { username: 'testuser', avatarUrl: 'https://example.com/avatar.png' },
    verdict: 'flagged',
    threatScore: 65,
    defconLevel: 3,
    executionTimeMs: 1250,
    status: 'completed',
};

describe('IncursionRow', () => {
    afterEach(() => cleanup());

    it('renders all data fields', () => {
        render(<IncursionRow {...mockProps} />);
        expect(screen.getByText('org/repo#42')).toBeInTheDocument();
        expect(screen.getByText('testuser')).toBeInTheDocument();
        expect(screen.getByText('FLAGGED')).toBeInTheDocument();
        expect(screen.getByText('65')).toBeInTheDocument();
        expect(screen.getByText('ELEVATED')).toBeInTheDocument();
        expect(screen.getByText('1250ms')).toBeInTheDocument();
        expect(screen.getByText('COMPLETED')).toBeInTheDocument();
    });

    it('calls onClick when clicked', () => {
        const handleClick = vi.fn();
        render(<IncursionRow {...mockProps} onClick={handleClick} />);
        fireEvent.click(screen.getByText('org/repo#42'));
        expect(handleClick).toHaveBeenCalledTimes(1);
    });

    it('applies custom className', () => {
        render(<IncursionRow {...mockProps} className="custom-row" />);
        expect(screen.getByText('org/repo#42').closest('button')).toHaveClass('custom-row');
    });
});