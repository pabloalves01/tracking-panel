import { differenceInCalendarDays, format, parseISO } from 'date-fns'

import { Badge } from '@/components/ui/badge'

/**
 * Validade de documento (CRLV, RNTRC) em três estados: vencido, vencendo
 * nos próximos `warnDays` dias e em dia. A data aparece sempre — a cor
 * não carrega a informação sozinha.
 */
export function ExpiryBadge({ date, warnDays = 30 }) {
    if (!date) {
        return <span className="text-muted-foreground text-xs">—</span>
    }

    const day = parseISO(date)
    const left = differenceInCalendarDays(day, new Date())
    const shown = format(day, 'dd/MM/yyyy')

    if (left < 0) {
        return <Badge variant="destructive">Vencido · {shown}</Badge>
    }

    if (left <= warnDays) {
        return (
            <Badge variant="outline" className="border-amber-500/50 text-amber-700 dark:text-amber-400">
                {shown} · {left === 0 ? 'vence hoje' : `em ${left} ${left === 1 ? 'dia' : 'dias'}`}
            </Badge>
        )
    }

    return <span className="text-sm tabular-nums">{shown}</span>
}
