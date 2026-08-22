import { describe, it, expect } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { DefconMeter } from '../DefconMeter';

describe('DefconMeter', () => {
    afterEach(() => cleanup());

    it('renders 5 segments', () => {
        render(<DefconMeter level={3} />);
        expect(screen.getByText('ELEVATED')).toBeInTheDocument();
    });

    it('shows correct label for each level', () => {
        const levels = [
            { level: 1, label: 'CRITICAL' },
            { level: 2, label: 'HIGH' },
            { level: 3, label: 'ELEVATED' },
            { level: 4, label: 'GUARDED' },
            { level: 5, label: 'LOW' },
        ];
        levels.forEach(({ level, label }) => {
            render(<DefconMeter level={level} />);
            expect(screen.getByText(label)).toBeInTheDocument();
            cleanup();
        });
    });

    it('applies size classes', () => {
        const sizes = ['sm', 'md', 'lg'];
        sizes.forEach((size) => {
            render(<DefconMeter level={3} size={size} />);
            expect(screen.getByText('ELEVATED')).toBeInTheDocument();
            cleanup();
        });
    });
});