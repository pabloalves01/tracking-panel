import { ChevronsUpDown, Fuel } from 'lucide-react'
import { useNavigate } from 'react-router'

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { formatCpfCnpj } from '@/helpers/formatters'
import { useStationSession } from '@/lib/auth/stationSession'

/**
 * Pílula com o posto atual. Trocar de contexto volta para a seleção.
 */
export function StationSwitcher() {
    const { station } = useStationSession()
    const navigate = useNavigate()
    const name = station?.trade_name || station?.corporate_name

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <button
                        type="button"
                        className="flex items-center gap-2 rounded-lg border bg-background px-3 py-1.5 text-sm transition-colors hover:bg-muted"
                    >
                        <Fuel className="size-4 shrink-0 text-muted-foreground" />
                        <span className="font-medium">{name}</span>
                        <span className="text-muted-foreground">{formatCpfCnpj(station?.cnpj)}</span>
                        <ChevronsUpDown className="size-4 shrink-0 text-muted-foreground" />
                    </button>
                }
            />
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuItem onClick={() => navigate('/auth/contextos')}>
                    Trocar contexto
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    )
}
