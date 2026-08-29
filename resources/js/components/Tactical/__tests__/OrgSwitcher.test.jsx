import { describe, it, expect, vi } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { OrgSwitcher } from '../OrgSwitcher';

describe('OrgSwitcher', () => {
    afterEach(() => cleanup());

    const organizations = [
        { id: 'org-1', name: 'Alpha', slug: 'alpha' },
        { id: 'org-2', name: 'Bravo', slug: 'bravo' },
    ];

    it('renders the list of organizations', () => {
        render(<OrgSwitcher organizations={organizations} currentOrganization={null} onSwitch={() => {}} />);

        expect(screen.getByText('Alpha')).toBeInTheDocument();
        expect(screen.getByText('Bravo')).toBeInTheDocument();
    });

    it('highlights the current organization', () => {
        render(
            <OrgSwitcher
                organizations={organizations}
                currentOrganization={{ id: 'org-2', name: 'Bravo', slug: 'bravo' }}
                onSwitch={() => {}}
            />
        );

        const bravo = screen.getByText('Bravo').closest('button');
        expect(bravo).toHaveAttribute('aria-current', 'true');
        expect(screen.getByText('Alpha').closest('button')).not.toHaveAttribute('aria-current');
    });

    it('calls onSwitch with the organization id on click', () => {
        const onSwitch = vi.fn();
        render(<OrgSwitcher organizations={organizations} currentOrganization={null} onSwitch={onSwitch} />);

        screen.getByText('Bravo').closest('button').click();

        expect(onSwitch).toHaveBeenCalledWith('org-2');
    });

    it('shows a placeholder when there are no organizations', () => {
        render(<OrgSwitcher organizations={[]} currentOrganization={null} onSwitch={() => {}} />);

        expect(screen.getByText('Nenhuma organização')).toBeInTheDocument();
    });
});
