import {
    Anchor,
    ArrowDownRight,
    ArrowUpRight,
    RefreshCcw,
    Ship,
    ShoppingCart,
    Truck,
    Wallet,
} from 'lucide-react'

import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { Badge } from '@/components/ui/badge'
import { useSession } from '@/lib/auth/session'

const indicators = [
    {
        label: 'Operações em andamento',
        value: '18',
        detail: '12 portuárias · 6 rodoviárias',
        icon: Ship,
        trend: '+3 hoje',
        up: true,
    },
    {
        label: 'Entregas do dia',
        value: '47',
        detail: '9 atrasadas',
        icon: Truck,
        trend: '-2 vs. ontem',
        up: false,
    },
    {
        label: 'Contas a receber',
        value: 'R$ 1,24 mi',
        detail: 'R$ 186 mil vencendo hoje',
        icon: Wallet,
        trend: '+8,4%',
        up: true,
    },
    {
        label: 'Pedidos de compra',
        value: '23',
        detail: '5 aguardando aprovação',
        icon: ShoppingCart,
        trend: '+4 esta semana',
        up: true,
    },
]

const operations = [
    { code: 'OP-1042', type: 'Portuária', description: 'Descarga · MV Atlantic Star', location: 'Porto de Santos — Berço 3', status: 'Em andamento' },
    { code: 'OP-1041', type: 'Rodoviária', description: 'Coleta de contêineres', location: 'Cubatão → São Paulo', status: 'Em trânsito' },
    { code: 'OP-1038', type: 'Portuária', description: 'Atracação · MSC Aurora', location: 'Porto de Paranaguá — Berço 1', status: 'Aguardando' },
    { code: 'OP-1035', type: 'Rodoviária', description: 'Entrega de carga geral', location: 'Curitiba → Joinville', status: 'Concluída' },
    { code: 'OP-1033', type: 'Portuária', description: 'Estufagem de contêineres', location: 'Terminal de Imbituba', status: 'Atrasada' },
]

const finance = [
    { title: 'Frete marítimo · MSC Aurora', kind: 'A receber', value: 'R$ 248.900', due: 'Vence hoje' },
    { title: 'Combustível · frota rodoviária', kind: 'A pagar', value: 'R$ 62.450', due: 'Vence em 2 dias' },
    { title: 'Armazenagem · Terminal de Santos', kind: 'A pagar', value: 'R$ 31.200', due: 'Vence em 5 dias' },
    { title: 'Frete rodoviário · lote 221', kind: 'A receber', value: 'R$ 18.760', due: 'Recebido' },
]

const statusVariant = {
    'Em andamento': 'default',
    'Em trânsito': 'secondary',
    Aguardando: 'outline',
    Concluída: 'secondary',
    Atrasada: 'destructive',
}

export default function Home() {
    const { user, currentCompany } = useSession()

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início' },
                ]}
                buttons={[
                    {
                        title: 'Atualizar',
                        icon: RefreshCcw,
                        variant: 'outline',
                        onClick: () => window.location.reload(),
                    },
                ]}
            />

            <header className="mb-6">
                <h1 className="text-3xl font-semibold tracking-tight">
                    Bem-vindo, {user?.name.split(' ')[0]}!
                </h1>
                <p className="mt-1 text-muted-foreground">
                    Visão geral das operações e do financeiro
                    {currentCompany ? ` de ${currentCompany.trade_name}` : ''}.
                </p>
            </header>

            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {indicators.map((item) => {
                    const Icon = item.icon
                    const TrendIcon = item.up ? ArrowUpRight : ArrowDownRight

                    return (
                        <div key={item.label} className="rounded-xl border bg-card p-5">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">{item.label}</span>
                                <Icon className="size-4 text-muted-foreground" />
                            </div>
                            <p className="mt-3 text-2xl font-semibold tracking-tight">{item.value}</p>
                            <div className="mt-1 flex items-center justify-between text-xs text-muted-foreground">
                                <span>{item.detail}</span>
                                <span className={`flex items-center gap-0.5 font-medium ${item.up ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'}`}>
                                    <TrendIcon className="size-3.5" />
                                    {item.trend}
                                </span>
                            </div>
                        </div>
                    )
                })}
            </section>

            <section className="mt-6 grid gap-4 lg:grid-cols-3">
                <div className="rounded-xl border bg-card lg:col-span-2">
                    <div className="flex items-center gap-2 border-b px-5 py-4">
                        <Anchor className="size-4 text-muted-foreground" />
                        <h2 className="font-medium">Operações recentes</h2>
                    </div>
                    <ul className="divide-y">
                        {operations.map((operation) => (
                            <li key={operation.code} className="flex items-center gap-4 px-5 py-3.5">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">{operation.description}</p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {operation.code} · {operation.type} · {operation.location}
                                    </p>
                                </div>
                                <Badge variant={statusVariant[operation.status]}>
                                    {operation.status}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="rounded-xl border bg-card">
                    <div className="flex items-center gap-2 border-b px-5 py-4">
                        <Wallet className="size-4 text-muted-foreground" />
                        <h2 className="font-medium">Financeiro</h2>
                    </div>
                    <ul className="divide-y">
                        {finance.map((entry) => (
                            <li key={entry.title} className="flex items-center gap-3 px-5 py-3.5">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">{entry.title}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {entry.kind} · {entry.due}
                                    </p>
                                </div>
                                <span className={`shrink-0 text-sm font-medium ${entry.kind === 'A receber' ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'}`}>
                                    {entry.value}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            </section>
        </>
    )
}
