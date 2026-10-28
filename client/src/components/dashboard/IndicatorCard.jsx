import { TrendingDown, TrendingUp } from 'lucide-react'
import { Area, AreaChart } from 'recharts'

import { Badge } from '@/components/ui/badge'
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import { ChartContainer } from '@/components/ui/chart'

/**
 * Indicador do topo da home.
 *
 * O número responde à pergunta; a sparkline mostra a direção dos últimos 14
 * dias sem gastar um gráfico inteiro. A sparkline não tem eixo nem rótulo de
 * propósito — ela é lida como forma, não como valor. O valor exato de cada
 * ponto está no gráfico grande logo abaixo.
 *
 * A cor do selo segue o que é bom para a operação, não a direção da seta:
 * entrega atrasada subindo é ruim mesmo com a seta para cima.
 */
export function IndicatorCard({ indicator }) {
    const { label, value, detail, delta, direction, good, series } = indicator
    const TrendIcon = direction === 'up' ? TrendingUp : TrendingDown
    const tone = good ? 'var(--status-good)' : 'var(--status-critical)'

    return (
        <Card className="@container/card gap-3">
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl font-semibold tabular-nums tracking-tight @[250px]/card:text-3xl">
                    {value}
                </CardTitle>
                <CardAction>
                    <Badge variant="outline" className="gap-1">
                        <TrendIcon aria-hidden className="size-3" />
                        {delta}
                    </Badge>
                </CardAction>
            </CardHeader>
            <CardContent className="flex items-end justify-between gap-3">
                <p className="text-xs text-muted-foreground">{detail}</p>

                <ChartContainer
                    config={{ value: { label, color: tone } }}
                    className="aspect-auto h-8 w-20 shrink-0"
                >
                    <AreaChart data={series} margin={{ top: 2, bottom: 2, left: 0, right: 0 }}>
                        <defs>
                            <linearGradient id={`spark-${indicator.key}`} x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor={tone} stopOpacity={0.3} />
                                <stop offset="100%" stopColor={tone} stopOpacity={0} />
                            </linearGradient>
                        </defs>
                        <Area
                            dataKey="value"
                            type="monotone"
                            stroke={tone}
                            strokeWidth={1.5}
                            fill={`url(#spark-${indicator.key})`}
                            dot={false}
                            isAnimationActive={false}
                        />
                    </AreaChart>
                </ChartContainer>
            </CardContent>
        </Card>
    )
}
