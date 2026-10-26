import { useEffect, useState } from 'react'
import { Building2, ChevronsUpDown, Fuel } from 'lucide-react'

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { formatCpfCnpj } from '@/helpers/formatters'
import { roleLabel } from '@/helpers/labels'
import { getAuthContexts, selectCompany, selectFuelStation } from '@/lib/auth/auth'
import { useSession } from '@/lib/auth/session'
import { notifyError } from '@/lib/notify'

function stationName(station) {
    return station.trade_name || station.corporate_name
}

/**
 * Pílula com o contexto atual (empresa ou posto). Ao abrir, lista empresas e postos
 * do usuário e troca na mesma sessão, sem voltar para a escolha de contexto.
 */
export function ContextSwitcher() {
    const { user } = useSession()
    const [contexts, setContexts] = useState({ companies: [], fuel_stations: [] })
    const [switching, setSwitching] = useState(false)

    const company = user.current_company
    const station = user.current_fuel_station
    const current = company ? `company:${company.uuid}` : station ? `station:${station.uuid}` : null
    const total = contexts.companies.length + contexts.fuel_stations.length

    useEffect(() => {
        getAuthContexts().then(setContexts).catch(() => {})
    }, [])

    // Admin da Logiv sem vínculo: não há o que trocar.
    if (!current && total === 0) return null

    async function handleSelect(value) {
        if (value === current) return

        const [type, uuid] = value.split(':')
        setSwitching(true)

        try {
            if (type === 'company') {
                await selectCompany(uuid)
            } else {
                await selectFuelStation(uuid)
            }
            // Tudo é escopado pelo contexto do token; recarregar refaz as buscas.
            window.location.assign(type === 'company' ? '/app' : '/posto')
        } catch (error) {
            notifyError(error)
            setSwitching(false)
        }
    }

    const Icon = station ? Fuel : Building2
    const label = company?.trade_name ?? (station ? stationName(station) : 'Escolher empresa ou posto')
    const document = (company ?? station)?.cnpj

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
                        <span className="font-medium">{label}</span>
                        {document ? <span className="text-muted-foreground">{formatCpfCnpj(document)}</span> : null}
                        <ChevronsUpDown className="size-4 shrink-0 text-muted-foreground" />
                    </button>
                }
            />
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuRadioGroup value={current} onValueChange={handleSelect}>
                    {contexts.companies.length ? (
                        <DropdownMenuGroup>
                            <DropdownMenuLabel>Empresas</DropdownMenuLabel>
                            {contexts.companies.map((item) => (
                                <ContextItem
                                    key={item.uuid}
                                    value={`company:${item.uuid}`}
                                    name={item.trade_name}
                                    cnpj={item.cnpj}
                                    role={item.role}
                                />
                            ))}
                        </DropdownMenuGroup>
                    ) : null}
                    {contexts.fuel_stations.length ? (
                        <DropdownMenuGroup>
                            <DropdownMenuLabel>Postos</DropdownMenuLabel>
                            {contexts.fuel_stations.map((item) => (
                                <ContextItem
                                    key={item.uuid}
                                    value={`station:${item.uuid}`}
                                    name={stationName(item)}
                                    cnpj={item.cnpj}
                                    role={item.role}
                                />
                            ))}
                        </DropdownMenuGroup>
                    ) : null}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    )
}

function ContextItem({ value, name, cnpj, role }) {
    return (
        <DropdownMenuRadioItem value={value}>
            <span className="flex min-w-0 flex-col">
                <span className="truncate font-medium">{name}</span>
                <span className="text-xs text-muted-foreground">
                    {formatCpfCnpj(cnpj)} · {roleLabel[role]}
                </span>
            </span>
        </DropdownMenuRadioItem>
    )
}
