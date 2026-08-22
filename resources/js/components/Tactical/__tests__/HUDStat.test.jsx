import { describe, it, expect } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { HUDStat } from '../HUDStat';

describe('HUDStat', () => {
    afterEach(() => cleanup());

    it('renders label and value', () => {
        render(<HUDStat label="Total PRs" value={42} />);
        expect(screen.getByText('Total PRs')).toBeInTheDocument();
        expect(screen.getByText('42')).toBeInTheDocument();
    });

    it('renders icon when provided', () => {
        render(<HUDStat label="Test" value={1} icon={<span data-testid="icon">ICON</span>} />);
        expect(screen.getByTestId('icon')).toBeInTheDocument();
    });

    it('shows trend up with green color', () => {
        render(<HUDStat label="Test" value={1} trend="up" trendValue="+5%" />);
        expect(screen.getByText('+5%')).toHaveClass('text-nv-green');
    });

    it('shows trend down with red color', () => {
        render(<HUDStat label="Test" value={1} trend="down" trendValue="-3%" />);
        expect(screen.getByText('-3%')).toHaveClass('text-defcon-red');
    });

    it('shows neutral trend', () => {
        render(<HUDStat label="Test" value={1} trend="neutral" />);
        expect(screen.getByText('Test')).toBeInTheDocument();
        expect(screen.getByText('1')).toBeInTheDocument();
    });
});