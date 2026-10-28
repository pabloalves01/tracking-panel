import { useState } from 'react'
import {
    Bar,
    BarChart,
    CartesianGrid,
    LabelList,
    Line,
    LineChart,
    XAxis,
    YAxis,
} from 'recharts'

import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { AttentionList } from '@/components/dashboard/AttentionList'
import { FleetStatusCard } from '@/components/dashboard/FleetStatusCard'
import { IndicatorCard } from '@/components/dashboard/IndicatorCard'
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart'
import {
    deliveriesByWeek,
    financeSummary,
    fuelByStation,
    indicators,
    needsAttention,
    operationsByDay,
} from '@/lib/dashboard/mockData'
import { useSession } from '@/lib/auth/session'

const operationsConfig = {
    port: { label: 'Portuária', color: 'var(--chart-1)' },
    road: { label: 'Rodoviária', color: 'var(--chart-2)' },
}

const deliveriesConfig = {
    onTime: { label: 'No prazo', color: 'var(--chart-1)' },
    late: { label: 'Atrasadas', color: 'var(--chart-2)' },
}

const fuelConfig = {
    liters: { label: 'Litros', color: 'var(--chart-3)' },
}

const rangeOptions = [
    { value: '30', label: 'Últimos 30 dias' },
    { value: '14', label: 'Últimos 14 dias' },
    { value: '7', label: 'Últimos 7 dias' },
]

const rangeLabel = {
    30: 'Últimos 30 dias',
    14: 'Últimos 14 dias',
    7: 'Últimos 7 dias',
}

export default function Home() {
    const { user, current_organization: organization } = useSession()
    const [range, setRange] = useState('30')
    const firstName = user?.name?.split(' ')[0]

    const operations = operationsByDay.slice(-Number(range))
    const criticalCount = needsAttention.filter((item) => item.severity === 'critical').length

    return (
        <>
            {/* <LayoutToolbar breadcrumbs={[{ text: 'Início' }]} /> */}

            <header className="mb-5">
                <h1 className="text-xl font-semibold tracking-tight">
                    {firstName ? `Bom trabalho, ${firstName}` : 'Visão geral'}
                </h1>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    {criticalCount > 0 ? (
                        <>
                            <span className="font-medium text-foreground">
                                {criticalCount} operações precisam de atenção agora
                            </span>
                            {' · '}
                        </>
                    ) : null}
                    18 operações em andamento
                    {organization ? ` em ${organization.display_name}` : ''}.
                </p>
            </header>

            {/* Indicadores. O número grande responde; a sparkline mostra se
                ele está subindo ou descendo, sem ocupar um gráfico inteiro. */}
            <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {indicators.map((indicator) => (
                    <IndicatorCard key={indicator.key} indicator={indicator} />
                ))}
            </section>

            <section className="mt-3 grid gap-3 lg:grid-cols-3">
                {/* Nível ao longo do tempo, duas séries: linha. Não área
                    empilhada — ler cada modal importa mais que o total. */}
                <Card className="@container/card lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Operações abertas por dia</CardTitle>
                        <CardDescription>
                            {rangeLabel[range]}, por modal
                        </CardDescription>
                        <CardAction>
                            <Select items={rangeOptions} value={range} onValueChange={setRange}>
                                <SelectTrigger size="sm" className="w-36" aria-label="Período">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {rangeOptions.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <ChartContainer config={operationsConfig} className="aspect-auto h-[232px] w-full">
                            <LineChart data={operations} margin={{ left: 4, right: 12, top: 4 }}>
                                <CartesianGrid vertical={false} strokeDasharray="3 3" />
                                <XAxis
                                    dataKey="label"
                                    tickLine={false}
                                    axisLine={false}
                                    tickMargin={8}
                                    minTickGap={28}
                                />
                                <YAxis
                                    tickLine={false}
                                    axisLine={false}
                                    tickMargin={8}
                                    width={28}
                                    allowDecimals={false}
                                />
                                <ChartTooltip content={<ChartTooltipContent />} />
                                <ChartLegend content={<ChartLegendContent />} />
                                <Line
                                    dataKey="port"
                                    type="monotone"
                                    stroke="var(--color-port)"
                                    strokeWidth={2}
                                    dot={false}
                                    activeDot={{ r: 4 }}
                                />
                                <Line
                                    dataKey="road"
                                    type="monotone"
                                    stroke="var(--color-road)"
                                    strokeWidth={2}
                                    dot={false}
                                    activeDot={{ r: 4 }}
                                />
                            </LineChart>
                        </ChartContainer>
                    </CardContent>
                </Card>

                {/* Partes de um todo, quatro categorias: barras horizontais.
                    Não rosca — comparar comprimento é mais fácil que ângulo. */}
                <FleetStatusCard />
            </section>

            <section className="mt-3 grid gap-3 lg:grid-cols-2">
                {/* Duas séries que somam o total de entregas: empilhar faz
                    sentido aqui, com 2px de respiro entre os segmentos. */}
                <Card>
                    <CardHeader>
                        <CardTitle>Entregas por semana</CardTitle>
                        <CardDescription>Oito semanas, prazo cumprido e descumprido</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ChartContainer config={deliveriesConfig} className="aspect-auto h-[200px] w-full">
                            <BarChart data={deliveriesByWeek} margin={{ left: 4, right: 4, top: 4 }}>
                                <CartesianGrid vertical={false} strokeDasharray="3 3" />
                                <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} />
                                <YAxis tickLine={false} axisLine={false} tickMargin={8} width={34} />
                                <ChartTooltip content={<ChartTooltipContent />} />
                                <ChartLegend content={<ChartLegendContent />} />
                                <Bar
                                    dataKey="onTime"
                                    stackId="deliveries"
                                    fill="var(--color-onTime)"
                                    stroke="var(--card)"
                                    strokeWidth={2}
                                    maxBarSize={38}
                                />
                                <Bar
                                    dataKey="late"
                                    stackId="deliveries"
                                    fill="var(--color-late)"
                                    stroke="var(--card)"
                                    strokeWidth={2}
                                    radius={[4, 4, 0, 0]}
                                    maxBarSize={38}
                                />
                            </BarChart>
                        </ChartContainer>
                    </CardContent>
                </Card>

                {/* Uma medida através de itens: barras horizontais, ordenadas.
                    Série única, então sem legenda — o título já a nomeia. */}
                <Card>
                    <CardHeader>
                        <CardTitle>Combustível por posto</CardTitle>
                        <CardDescription>Litros abastecidos no mês</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ChartContainer config={fuelConfig} className="aspect-auto h-[200px] w-full">
                            <BarChart
                                data={fuelByStation}
                                layout="vertical"
                                margin={{ left: 4, right: 56, top: 4 }}
                            >
                                <XAxis type="number" hide />
                                <YAxis
                                    type="category"
                                    dataKey="station"
                                    tickLine={false}
                                    axisLine={false}
                                    tickMargin={6}
                                    width={120}
                                />
                                <ChartTooltip
                                    content={
                                        <ChartTooltipContent
                                            formatter={(value) => (
                                                <span className="font-mono tabular-nums">
                                                    {value.toLocaleString('pt-BR')} L
                                                </span>
                                            )}
                                        />
                                    }
                                />
                                <Bar
                                    dataKey="liters"
                                    fill="var(--color-liters)"
                                    radius={[0, 4, 4, 0]}
                                    barSize={14}
                                >
                                    <LabelList
                                        dataKey="liters"
                                        position="right"
                                        offset={8}
                                        className="fill-muted-foreground"
                                        fontSize={11}
                                        formatter={(value) =>
                                            `${(value / 1000).toLocaleString('pt-BR', {
                                                minimumFractionDigits: 1,
                                                maximumFractionDigits: 1,
                                            })} mil L`
                                        }
                                    />
                                </Bar>
                            </BarChart>
                        </ChartContainer>
                    </CardContent>
                </Card>
            </section>

            <section className="mt-3 grid gap-3 lg:grid-cols-3">
                <AttentionList items={needsAttention} className="lg:col-span-2" />

                <Card>
                    <CardHeader>
                        <CardTitle>Financeiro</CardTitle>
                        <CardDescription>Posição de hoje</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div>
                            <p className="text-xs text-muted-foreground">A receber</p>
                            <p className="mt-0.5 text-2xl font-semibold tabular-nums tracking-tight">
                                {financeSummary.receivable}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {financeSummary.receivableToday}
                            </p>
                        </div>
                        <div className="border-t pt-4">
                            <p className="text-xs text-muted-foreground">A pagar</p>
                            <p className="mt-0.5 text-2xl font-semibold tabular-nums tracking-tight">
                                {financeSummary.payable}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {financeSummary.payableToday}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </>
    )
}
