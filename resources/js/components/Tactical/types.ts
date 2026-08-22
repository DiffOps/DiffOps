import type { ReactNode } from 'react';

export interface BadgeProps {
    variant?: 'clear' | 'flagged' | 'hostile' | 'neutral' | 'scanning' | 'completed' | 'failed';
    children: ReactNode;
    className?: string;
}

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'primary' | 'danger' | 'ghost' | 'metallic';
    size?: 'sm' | 'md' | 'lg';
    loading?: boolean;
    leftIcon?: ReactNode;
    rightIcon?: ReactNode;
}

export interface CardProps {
    children: ReactNode;
    className?: string;
    variant?: 'default' | 'elevated' | 'outlined';
    corners?: boolean;
}

export interface HUDStatProps {
    label: string;
    value: string | number;
    icon?: ReactNode;
    trend?: 'up' | 'down' | 'neutral';
    trendValue?: string;
    className?: string;
}

export interface PillProps {
    variant?: 'nv-green' | 'amber' | 'defcon-red' | 'comms-cyan' | 'dusk';
    children: ReactNode;
    className?: string;
}

export interface ThreatMeterProps {
    score: number;
    size?: number;
    strokeWidth?: number;
    showLabel?: boolean;
    className?: string;
}

export interface DefconMeterProps {
    level: 1 | 2 | 3 | 4 | 5;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}

export interface VerdictBadgeProps {
    verdict: 'clear' | 'flagged' | 'hostile';
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}

export interface DiffLine {
    type: 'add' | 'remove' | 'context';
    content: string;
    lineNumber?: { old?: number; new?: number };
    findingId?: string;
}

export interface DiffViewerProps {
    lines: DiffLine[];
    maxHeight?: string;
    showLineNumbers?: boolean;
    highlightFinding?: string;
    onFindingClick?: (findingId: string) => void;
    className?: string;
}

export interface StatusPillProps {
    status: 'scanning' | 'completed' | 'failed' | 'idle';
    label?: string;
    className?: string;
}

export interface IncursionRowProps {
    id: string;
    timestamp: string;
    repository: string;
    prNumber: number;
    author: { username: string; avatarUrl?: string };
    verdict: 'clear' | 'flagged' | 'hostile';
    threatScore: number;
    defconLevel: 1 | 2 | 3 | 4 | 5;
    executionTimeMs: number;
    status: 'scanning' | 'completed' | 'failed';
    onClick?: () => void;
    className?: string;
}