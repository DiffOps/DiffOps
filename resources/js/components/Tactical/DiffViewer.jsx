import { useRef, useEffect, useState, useMemo } from 'react';
import { FixedSizeList as List } from 'react-window';
import { ChevronRight } from 'lucide-react';

const LINE_HEIGHT = 20;
const GUTTER_WIDTH = 48;
const FINDING_MARKER_WIDTH = 16;

export function DiffViewer({
    lines,
    maxHeight = '60vh',
    showLineNumbers = true,
    highlightFinding,
    onFindingClick,
    className = '',
}) {
    const [containerWidth, setContainerWidth] = useState(800);
    const listRef = useRef<List>(null);

    useEffect(() => {
        const handleResize = () => setContainerWidth(window.innerWidth);
        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);

    const visibleLines = useMemo(() => lines, [lines]);

    const LineRow = ({ index, style }) => {
        const line = visibleLines[index];
        if (!line) return null;

        const isAdd = line.type === 'add';
        const isRemove = line.type === 'remove';
        const isContext = line.type === 'context';
        const isHighlighted = line.findingId && line.findingId === highlightFinding;

        const bgColor = isHighlighted
            ? 'bg-amber/20'
            : isAdd
            ? 'bg-nv-green/5'
            : isRemove
            ? 'bg-defcon-red/5'
            : 'bg-transparent';

        const textColor = isAdd
            ? 'text-nv-green'
            : isRemove
            ? 'text-defcon-red'
            : 'text-dusk';

        const gutterBg = isAdd
            ? 'bg-nv-green/10'
            : isRemove
            ? 'bg-defcon-red/10'
            : 'bg-transparent';

        return (
            <div
                style={style}
                className={`flex ${bgColor} border-b border-graphite/50 ${isHighlighted ? 'ring-1 ring-amber' : ''}`}
                data-finding-id={line.findingId}
            >
                {showLineNumbers && (
                    <div className={`flex items-center justify-end w-${GUTTER_WIDTH} px-2 font-mono text-[11px] text-barrel select-none ${gutterBg} border-r border-graphite/50`}>
                        <span className="w-10 text-right pr-2">{line.lineNumber?.old ?? '—'}</span>
                        <span className="w-10 text-right text-dusk">{line.lineNumber?.new ?? '—'}</span>
                    </div>
                )}
                {line.findingId && (
                    <button
                        onClick={(e) => { e.stopPropagation(); onFindingClick?.(line.findingId); }}
                        className={`flex items-center justify-center w-${FINDING_MARKER_WIDTH} text-amber hover:text-amber/50 transition-colors`}
                        aria-label={`Finding: ${line.findingId}`}
                    >
                        <ChevronRight className="h-3 w-3" />
                    </button>
                )}
                <div className={`flex-1 px-3 py-0.5 font-mono text-sm ${textColor} whitespace-pre overflow-x-auto`}>
                    {line.content || ' '}
                </div>
            </div>
        );
    };

    const itemCount = visibleLines.length;
    const itemSize = LINE_HEIGHT;

    return (
        <div
            className={`bg-obsidian border border-graphite rounded-lg font-mono overflow-hidden ${className}`}
            style={{ maxHeight, width: '100%' }}
            onResize={(e) => setContainerWidth(e.target.offsetWidth)}
        >
            <List
                ref={listRef}
                height={Math.min(itemCount * itemSize, parseInt(maxHeight, 10)) || 400}
                itemCount={itemCount}
                itemSize={itemSize}
                width={containerWidth}
                overscanCount={10}
            >
                {LineRow}
            </List>
        </div>
    );
}