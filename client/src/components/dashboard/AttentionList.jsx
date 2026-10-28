import { AlertTriangle, CircleAlert } from 'lucide-react'
import { Link } from 'react-router'

import { Button } from '@/components/ui/button'
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'

/**
 * As operações que o gestor precisa olhar agora.
 *
 * É a primeira coisa que ele procura ao abrir o sistema, então o motivo vem
 * escrito por extenso — "Atrasada há 6h", não um ponto vermelho. A gravidade
 * aparece em ícone e em texto; a cor é reforço, nunca a única informação.
 */

const severity = {
    critical: {
        icon: CircleAlert,
        color: 'var(--status-critical)',
        label: 'Crítico',
    },
    warning: {
        icon: AlertTriangle,
        color: 'var(--status-warning)',
        label: 'Atenção',
    },
}

export function AttentionList({ items, className }) {
    return (
        <Card className={className}>
            <CardHeader className="border-b">
                <CardTitle>Precisam de atenção</CardTitle>
                <CardDescription>
                    {items.length} operações fora do esperado
                </CardDescription>
                <CardAction>
                    <Button variant="ghost" size="sm" nativeButton={false} render={<Link to="/app/operacoes" />}>
                        Ver todas
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent className="px-0">
                <ul className="divide-y">
                    {items.map((item) => {
                        const level = severity[item.severity] ?? severity.warning
                        const Icon = level.icon

                        return (
                            <li key={item.code} className="flex items-start gap-3 px-4 py-2.5">
                                <Icon
                                    aria-hidden
                                    className="mt-0.5 size-4 shrink-0"
                                    style={{ color: level.color }}
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {item.description}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {item.code} · {item.mode} · {item.location}
                                    </p>
                                </div>
                                <span
                                    className="shrink-0 text-xs font-medium"
                                    style={{ color: level.color }}
                                >
                                    <span className="sr-only">{level.label}: </span>
                                    {item.reason}
                                </span>
                            </li>
                        )
                    })}
                </ul>
            </CardContent>
        </Card>
    )
}
