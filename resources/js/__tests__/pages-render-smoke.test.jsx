import { describe, it, expect, beforeEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';

// Render-smoke: renderiza CADA Page com props fiéis aos controllers.
// Pega a classe de bug useForm/Plus (crash de render derruba o bundle
// inteiro por causa do eager glob no app.jsx).
const pages = import.meta.glob('../Pages/**/*.jsx', { eager: true });

const AUTHOR = { username: 'carlosegoulart', avatarUrl: null };

const SAMPLE_INCURSION = {
    id: 'a1',
    timestamp: '2026-08-24T12:00:00Z',
    repository: 'acme/web',
    prNumber: 7,
    author: AUTHOR,
    verdict: 'flagged',
    threatScore: 55,
    defconLevel: 3,
    executionTimeMs: 1200,
    status: 'completed',
};

const PROPS_BY_PAGE = {
    'Dashboard': {
        auth: { user: { id: 'u1', name: 'Carlos', email: 'c@d.test', avatar_url: null } },
        stats: { totalOpenPRs: 3, avgThreatScore: 42, currentDefcon: 3, avgExecutionTimeMs: 900 },
        incursions: [SAMPLE_INCURSION],
        realtime: { channel: 'org:o1:analyses' },
    },
    'Incursions/Index': {
        incursions: [SAMPLE_INCURSION],
    },
    'Incursions/Show': {
        analysis: {
            id: 'a1',
            timestamp: '2026-08-24T12:00:00Z',
            repository: 'acme/web',
            prNumber: 7,
            headSha: 'abc123',
            author: { username: AUTHOR.username, avatarUrl: null, riskFingerprint: null },
            verdict: 'flagged',
            threatScore: 55,
            defconLevel: 3,
            riskLevel: 'medium',
            summary: 'Análise concluída.',
            executionTimeMs: 1200,
            isDegraded: false,
            complianceChecks: [],
            findings: [],
        },
        repository: { id: 'r1', commentOnPr: false },
    },
    'Repositories/Index': {
        repositories: {
            data: [{
                id: 'r1', name: 'web', full_name: 'acme/web', owner_login: 'acme',
                html_url: 'https://github.com/acme/web', github_repo_id: 1,
                is_active: true, comment_on_pr: false, escalate_on_hostile: false,
                escalation_webhook_url: null, security_level: 'standard',
                webhook_status: 'connected', last_incursion_at: null,
            }],
            current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null,
        },
        webhookUrl: 'http://localhost/api/webhooks/github',
    },
    'OperationsLog/Index': {
        logs: {
            data: [{
                id: 'l1', timestamp: '2026-08-24T12:00:00Z', action: 'analyzed',
                entity_type: 'analysis', entity_id: 'a1', user: null, payload: {},
            }],
            current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null,
        },
        filters: { actions: ['analyzed'], entityTypes: ['analysis'] },
    },
    'Briefing/Index': {
        period: { days: 30, since: '2026-07-25T00:00:00Z' },
        verdictDistribution: { clear: 4, flagged: 2, hostile: 1 },
        threatHistogram: Array.from({ length: 10 }, (_, i) => ({ range: `${i * 10}-${i * 10 + 9}`, count: i })),
        defconTrend: [{ date: '2026-08-01', avg_defcon: 3.2, avg_execution_time_ms: 800 }],
        findingsByCategory: { secret: { critical: 1 }, downgrade: { medium: 2 } },
        topRepos: [{ repo: 'acme/web', count: 5, hostile: 1, flagged: 2 }],
    },
    'Watchlist/Index': {
        watchlist: [],
        realtime: { channel: 'user:u1:watchlist' },
    },
    'Settings/Index': {
        user: { username: 'carlosegoulart', email: 'c@d.test', avatar_url: null, role: 'operator' },
        preferences: { theme: 'tactical', notifications: { email: false, push: false, realtime: true } },
    },
    'Auth/Login': {},
    'Auth/Register': {},
    'Auth/Callback': {},
};

describe('Pages render smoke', () => {
    beforeEach(() => {
        globalThis.__pageProps = {};
        cleanup();
    });

    for (const [name, Component] of Object.entries(pages)) {
        it(`renders ${name}`, () => {
            const short = name.replace('../Pages/', '').replace('.jsx', '');
            globalThis.__pageProps = PROPS_BY_PAGE[short] ?? {};

            const Page = Component.default ?? Component;
            const { container } = render(<Page />);

            expect(container.firstElementChild).not.toBeNull();
        });
    }
});
