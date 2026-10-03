import { cn } from 'cn';
import { formatValue } from '../lib/format';
import type { StatsNode } from '../protocol';
import { Card, CardDescription, CardHeader, CardTitle } from '../ui/card';
import { toneDot, toneText } from './status-badge';

/** The stats node: a strip of number cards, each with an optional tone and sparkline. */
export function SchemaStats({ node }: { node: StatsNode }) {
    return (
        <div className={cn('grid grid-cols-2 gap-3 @3xl:gap-4', node.items.length >= 5 ? '@xl:grid-cols-3 @4xl:grid-cols-5' : '@xl:grid-cols-4')}>
            {node.items.map((stat) => {
                const alarming = Boolean(stat.value) && (stat.tone === 'danger' || stat.tone === 'warning');

                return (
                    <Card key={stat.label} className="from-primary/5 to-card dark:bg-card gap-1.5 bg-linear-to-t py-4 shadow-xs">
                        <CardHeader className="px-4">
                            <CardDescription className="flex items-center gap-1.5 truncate">
                                {stat.tone && <span className={cn('size-1.5 shrink-0 rounded-full', toneDot(stat.tone))} />}
                                {stat.label}
                            </CardDescription>
                            <CardTitle className={cn('text-2xl font-semibold tabular-nums', alarming && toneText(stat.tone))}>
                                {formatValue(stat.value, stat.format)}
                            </CardTitle>
                            {stat.description && <p className="text-muted-foreground truncate text-xs">{stat.description}</p>}
                        </CardHeader>
                        {stat.chart && stat.chart.length > 1 && <Sparkline points={stat.chart} tone={stat.tone} />}
                    </Card>
                );
            })}
        </div>
    );
}

/** Plain SVG, so stat cards don't need the chart library. */
function Sparkline({ points, tone }: { points: (number | null)[]; tone?: StatsNode['items'][number]['tone'] }) {
    const values = points.map((p) => p ?? 0);
    const max = Math.max(...values);
    const min = Math.min(...values, 0);
    const y = (v: number) => 26 - ((v - min) / (max - min || 1)) * 24;
    const line = values.map((v, i) => `${(i / (values.length - 1)) * 100},${y(v)}`).join(' ');
    const color = tone ? `var(--tone-${tone})` : 'var(--muted-foreground)';

    return (
        <svg viewBox="0 0 100 28" preserveAspectRatio="none" className="mx-4 h-8 w-[calc(100%-2rem)]" aria-hidden>
            <polygon points={`0,28 ${line} 100,28`} fill={color} opacity={0.12} />
            <polyline points={line} fill="none" stroke={color} strokeWidth={1.5} vectorEffect="non-scaling-stroke" strokeLinejoin="round" />
        </svg>
    );
}
