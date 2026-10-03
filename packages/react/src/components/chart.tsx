import { useId } from 'react';
import { Area, AreaChart, Bar, BarChart, CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import { formatValue, locale } from '../lib/format';
import type { ChartNode } from '../protocol';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../ui/card';
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent, type ChartConfig } from '../ui/chart';

function formatX(value: unknown, format: ChartNode['x_format']): string {
    if (!format) return String(value);
    const date = new Date(String(value));

    return format === 'hour'
        ? date.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })
        : date.toLocaleDateString(locale, { day: 'numeric', month: 'short' });
}

/** The chart node. Loaded lazily from the registry, so pages without charts don't download Recharts. */
export default function SchemaChart({ node }: { node: ChartNode }) {
    // Gradient ids are document-wide, so two area charts with a 'value' series would otherwise share one.
    const id = useId();
    const config: ChartConfig = Object.fromEntries(
        node.series.map((series, i) => [
            series.key,
            { label: series.label, color: series.tone ? `var(--tone-${series.tone})` : `var(--chart-${(i % 5) + 1})` },
        ]),
    );
    const stackId = node.stacked ? 'stack' : undefined;
    const tick = (value: unknown) => formatX(value, node.x_format);
    const decorations = [
        <CartesianGrid key="grid" vertical={false} />,
        <XAxis key="x" dataKey="x" tickLine={false} axisLine={false} tickMargin={8} minTickGap={28} tickFormatter={tick} />,
        <YAxis
            key="y"
            tickLine={false}
            axisLine={false}
            // 0–1 ratios need ticks between 0% and 100%.
            allowDecimals={node.format === 'percent'}
            width={node.format === 'duration' || node.format?.startsWith('money') ? 64 : 48}
            tickFormatter={(value) => formatValue(value, node.format ?? 'number')}
        />,
        <ChartTooltip key="tooltip" content={<ChartTooltipContent labelFormatter={tick} indicator={node.kind === 'bar' ? 'dot' : 'line'} />} />,
        node.series.length > 1 ? <ChartLegend key="legend" content={<ChartLegendContent />} /> : null,
    ];

    const chart =
        node.kind === 'bar' ? (
            <BarChart data={node.data} accessibilityLayer>
                {decorations}
                {node.series.map((series, i) => (
                    // A 1px card-coloured stroke leaves a 2px gap between stacked segments and adjacent bars.
                    <Bar
                        key={series.key}
                        dataKey={series.key}
                        stackId={stackId}
                        fill={`var(--color-${series.key})`}
                        stroke="var(--card)"
                        strokeWidth={1}
                        radius={!stackId || i === node.series.length - 1 ? [4, 4, 0, 0] : 0}
                    />
                ))}
            </BarChart>
        ) : node.kind === 'line' ? (
            <LineChart data={node.data} accessibilityLayer>
                {decorations}
                {node.series.map((series) => (
                    <Line
                        key={series.key}
                        dataKey={series.key}
                        type="monotone"
                        stroke={`var(--color-${series.key})`}
                        strokeWidth={2}
                        dot={false}
                        connectNulls={false}
                    />
                ))}
            </LineChart>
        ) : (
            <AreaChart data={node.data} accessibilityLayer>
                <defs>
                    {node.series.map((series) => (
                        <linearGradient key={series.key} id={`${id}-fill-${series.key}`} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor={`var(--color-${series.key})`} stopOpacity={0.5} />
                            <stop offset="95%" stopColor={`var(--color-${series.key})`} stopOpacity={0.05} />
                        </linearGradient>
                    ))}
                </defs>
                {decorations}
                {node.series.map((series) => (
                    <Area
                        key={series.key}
                        dataKey={series.key}
                        type="monotone"
                        stackId={stackId}
                        stroke={`var(--color-${series.key})`}
                        fill={`url(#${CSS.escape(`${id}-fill-${series.key}`)})`}
                        strokeWidth={2}
                    />
                ))}
            </AreaChart>
        );

    return (
        <Card className="gap-4 py-4">
            <CardHeader className="px-4">
                <CardTitle>{node.heading}</CardTitle>
                {node.description && <CardDescription>{node.description}</CardDescription>}
            </CardHeader>
            <CardContent className="px-2 sm:px-4">
                <ChartContainer config={config} className="aspect-auto w-full" style={{ height: node.height }}>
                    {chart}
                </ChartContainer>
            </CardContent>
        </Card>
    );
}
