import { describe, it, expect } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { ThreatMeter } from '../ThreatMeter';

describe('ThreatMeter', () => {
    afterEach(() => cleanup());

    it('renders SVG element', () => {
        render(<ThreatMeter score={50} />);
        const svg = screen.getByTestId('threat-meter-svg');
        expect(svg).toBeInTheDocument();
    });

    it('applies custom size', () => {
        render(<ThreatMeter score={50} size={120} />);
        const svg = screen.getByTestId('threat-meter-svg');
        expect(svg).toHaveAttribute('width', '120');
        expect(svg).toHaveAttribute('height', '120');
    });
});