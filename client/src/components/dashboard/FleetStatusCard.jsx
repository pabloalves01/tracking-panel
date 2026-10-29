import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import { fleetStatus } from '@/lib/dashboard/mockData'

/**
 * Situação da frota agora.
 *
 * São partes de um todo com quatro categorias, então barras horizontais e
 * não uma rosca: comparar comprimento é mais fácil que comparar ângulo, e a
 * barra ainda acomoda o rótulo e o número na mesma linha.
 *
 * As cores aqui são de estado, não de série — reservadas, e sempre
 * acompanhadas do rótulo em texto. Nunca só a cor.
 */

const toneColor = {
    good: 'var(--status-good)',
    neutral: 'var(--muted-foreground)',
    warning: 'var(--status-warning)',
    critical: 'var(--status-critical)',
}

export function FleetStatusCard({ className }) {
    const total = fleetStatus.reduce((sum, item) => sum + item.vehicles, 0)

    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle>Frota agora</CardTitle>
                <CardDescription>{total} veículos rastreados</CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                {fleetStatus.map((item) => {
                    const share = Math.round((item.vehicles / total) * 100)

                    return (
                        <div key={item.status} className="grid gap-1.5">
                            <div className="flex items-baseline justify-between gap-2 text-xs">
                                <span className="text-muted-foreground">{item.status}</span>
                                <span className="font-medium tabular-nums">
                                    {item.vehicles}
                                    <span className="ml-1 font-normal text-muted-foreground">
                                        {share}%
                                    </span>
                                </span>
                            </div>
                            <div
                                className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                                role="img"
                                aria-label={`${item.status}: ${item.vehicles} de ${total} veículos`}
                            >
                                <div
                                    className="h-full rounded-full"
                                    style={{
                                        width: `${share}%`,
                                        backgroundColor: toneColor[item.tone],
                                    }}
                                />
                            </div>
                        </div>
                    )
                })}
            </CardContent>
        </Card>
    )
}
