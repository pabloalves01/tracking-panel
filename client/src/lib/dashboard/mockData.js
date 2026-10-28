/**
 * Dados de demonstração da home.
 *
 * Tudo aqui é inventado e fica no front até a API de operações existir.
 * Quando ela chegar, este arquivo sai inteiro e as telas passam a consumir
 * o serviço — nenhum componente lê números fixos direto.
 */

const DAY = 24 * 60 * 60 * 1000

/**
 * Ruído determinístico em [-1, 1]: o mesmo desenho a cada carregamento, mas
 * sem o platô quadrado que uma senoide pura produz. Contagem de operação
 * real oscila dia a dia; sem isso o gráfico denuncia que é dado inventado.
 */
function noise(index, seed) {
    const value = Math.sin((index + 1) * 12.9898 + seed * 78.233) * 43758.5453
    return (value - Math.floor(value)) * 2 - 1
}

/** Série determinística: tendência suave mais variação diária. */
function wave(index, base, amplitude, period, phase = 0, seed = 1) {
    const trend = base + amplitude * Math.sin((index + phase) / period)
    const jitter = amplitude * 0.45 * noise(index, seed)

    return Math.max(0, Math.round(trend + jitter))
}

function dayLabel(date) {
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })
}

/** Operações abertas por dia nos últimos 30 dias, por modal. */
export const operationsByDay = Array.from({ length: 30 }, (_, index) => {
    const date = new Date(Date.now() - (29 - index) * DAY)
    const weekend = [0, 6].includes(date.getDay())
    const slowdown = weekend ? 0.45 : 1

    return {
        date: date.toISOString().slice(0, 10),
        label: dayLabel(date),
        port: Math.round(wave(index, 11, 4, 9, 0, 3) * slowdown),
        road: Math.round(wave(index, 17, 6, 7, 2, 11) * slowdown),
    }
})

/** Entregas das últimas 8 semanas, separadas por cumprimento do prazo. */
export const deliveriesByWeek = Array.from({ length: 8 }, (_, index) => {
    const onTime = wave(index, 212, 34, 2.6)
    const late = wave(index, 31, 17, 1.9, 1)

    return {
        label: `S${index + 1}`,
        onTime,
        late,
    }
})

/** Situação da frota neste momento. */
export const fleetStatus = [
    { status: 'Em trânsito', vehicles: 42, tone: 'good' },
    { status: 'Parado em pátio', vehicles: 9, tone: 'neutral' },
    { status: 'Em manutenção', vehicles: 5, tone: 'warning' },
    { status: 'Sem sinal há 2h+', vehicles: 2, tone: 'critical' },
]

/** Litros abastecidos no mês, por posto conveniado. */
export const fuelByStation = [
    { station: 'Rodovia BR-116', liters: 18420 },
    { station: 'Santos Terminal', liters: 15180 },
    { station: 'Curitiba Norte', liters: 11960 },
    { station: 'Imbituba', liters: 9340 },
    { station: 'Paranaguá', liters: 7210 },
    { station: 'Joinville Sul', liters: 4880 },
]

/** Sparklines dos indicadores: 14 pontos, sem eixo. */
const spark = (base, amplitude, period, phase) =>
    Array.from({ length: 14 }, (_, index) => ({
        index,
        value: wave(index, base, amplitude, period, phase),
    }))

export const indicators = [
    {
        key: 'operations',
        label: 'Operações em andamento',
        value: '18',
        detail: '12 portuárias · 6 rodoviárias',
        delta: '+3 hoje',
        direction: 'up',
        good: true,
        series: spark(16, 4, 3.4, 0),
    },
    {
        key: 'onTime',
        label: 'Entregas no prazo',
        value: '83%',
        detail: '9 atrasadas de 54',
        delta: '-4 p.p. na semana',
        direction: 'down',
        good: false,
        series: spark(85, 6, 4.1, 2),
    },
    {
        key: 'fleet',
        label: 'Frota em trânsito',
        value: '42/58',
        detail: '5 em manutenção',
        delta: '+2 vs. ontem',
        direction: 'up',
        good: true,
        series: spark(40, 7, 3.8, 1),
    },
    {
        key: 'fuel',
        label: 'Consumo médio',
        value: '2,8 km/l',
        detail: '66,9 mil litros no mês',
        delta: '+0,1 km/l',
        direction: 'up',
        good: true,
        series: spark(28, 3, 4.6, 3),
    },
]

/** As operações que o gestor precisa olhar agora. */
export const needsAttention = [
    {
        code: 'OP-1033',
        description: 'Estufagem de contêineres',
        mode: 'Portuária',
        location: 'Terminal de Imbituba',
        reason: 'Atrasada há 6h',
        severity: 'critical',
    },
    {
        code: 'OP-1041',
        description: 'Coleta de contêineres',
        mode: 'Rodoviária',
        location: 'Cubatão → São Paulo',
        reason: 'Sem sinal do rastreador há 2h',
        severity: 'critical',
    },
    {
        code: 'OP-1038',
        description: 'Atracação · MSC Aurora',
        mode: 'Portuária',
        location: 'Paranaguá — Berço 1',
        reason: 'Janela encerra em 90 min',
        severity: 'warning',
    },
    {
        code: 'OP-1044',
        description: 'Entrega de carga geral',
        mode: 'Rodoviária',
        location: 'Curitiba → Joinville',
        reason: 'CNH do motorista vence amanhã',
        severity: 'warning',
    },
    {
        code: 'OP-1047',
        description: 'Transferência de frota',
        mode: 'Rodoviária',
        location: 'Santos → Cubatão',
        reason: 'Aguardando aprovação de compra',
        severity: 'warning',
    },
]

/** Resumo financeiro do dia. */
export const financeSummary = {
    receivable: 'R$ 1,24 mi',
    receivableToday: 'R$ 186 mil vencendo hoje',
    payable: 'R$ 412 mil',
    payableToday: 'R$ 62 mil vencendo hoje',
}
