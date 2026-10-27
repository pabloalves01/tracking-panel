import { useState } from 'react'
import { Building2, ChevronsUpDown, Fuel } from 'lucide-react'
import { Link, useNavigate } from 'react-router'

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { formatCpfCnpj } from '@/helpers/formatters'
import { roleLabel } from '@/helpers/labels/roleLabel'
import { useSession } from '@/lib/auth/session'

/**
 * Pílula do contexto atual. Lista empresas e postos da mesma conta.
 */
export function ContextSwitcher() {
    const navigate = useNavigate()
    const { user, currentCompany, companies = [], stations = [], switchCompany, switchStation } = useSession()
    const [switching, setSwitching] = useState(false)
    const currentStation = user?.current_fuel_station
    const onStation = Boolean(currentStation)

    if (!companies.length && !stations.length) {
        return (
            <Link
                to="/auth/contextos"
                className="flex items-center gap-2 rounded-lg border bg-background px-3 py-1.5 text-sm transition-colors hover:bg-muted"
            >
                <Building2 className="size-4 shrink-0 text-muted-foreground" />
                <span className="font-medium">Selecionar contexto</span>
            </Link>
        )
    }

    const title = onStation
        ? (currentStation.trade_name || currentStation.corporate_name)
        : (currentCompany?.trade_name || 'Selecionar contexto')
    const document = onStation ? currentStation.cnpj : currentCompany?.cnpj
    const Icon = onStation ? Fuel : Building2

    async function select(action) {
        setSwitching(true)
        try {
            await action()
        } finally {
            setSwitching(false)
        }
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                disabled={switching}
                render={
                    <button
                        type="button"
                        className="flex items-center gap-2 rounded-lg border bg-background px-3 py-1.5 text-sm transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-50"
                    >
                        <Icon className="size-4 shrink-0 text-muted-foreground" />
                        <span className="font-medium">{title}</span>
                        <span className="text-muted-foreground">{formatCpfCnpj(document)}</span>
                        <ChevronsUpDown className="size-4 shrink-0 text-muted-foreground" />
                    </button>
                }
            />
            <DropdownMenuContent align="end" className="w-72">
                {companies.length > 0 ? (
                    <DropdownMenuGroup>
                        <DropdownMenuLabel>Empresas</DropdownMenuLabel>
                        {companies.map((company) => (
                            <DropdownMenuItem
                                key={company.uuid}
                                onClick={() => select(() => switchCompany(company.uuid))}
                            >
                                <ContextLine
                                    icon={Building2}
                                    title={company.trade_name}
                                    detail={roleLabel[company.role]}
                                    document={company.cnpj}
                                    current={!onStation && company.uuid === currentCompany?.uuid}
                                />
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                ) : null}
                {companies.length > 0 && stations.length > 0 ? <DropdownMenuSeparator /> : null}
                {stations.length > 0 ? (
                    <DropdownMenuGroup>
                        <DropdownMenuLabel>Postos</DropdownMenuLabel>
                        {stations.map((station) => (
                            <DropdownMenuItem
                                key={station.uuid}
                                onClick={() => select(() => switchStation(station.uuid))}
                            >
                                <ContextLine
                                    icon={Fuel}
                                    title={station.trade_name || station.corporate_name}
                                    detail={roleLabel[station.role]}
                                    document={station.cnpj}
                                    current={onStation && station.uuid === currentStation?.uuid}
                                />
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                ) : null}
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => navigate('/auth/contextos')}>
                    Ver contextos e convites
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    )
}

function ContextLine({ icon: Icon, title, detail, document, current }) {
    return (
        <span className="flex min-w-0 items-start gap-2">
            <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <span className="flex min-w-0 flex-col">
                <span className="truncate font-medium">
                    {title}
                    {current ? ' · atual' : ''}
                </span>
                {detail ? <span className="truncate text-xs text-muted-foreground">{detail}</span> : null}
                <span className="text-xs text-muted-foreground">{formatCpfCnpj(document)}</span>
            </span>
        </span>
    )
}
