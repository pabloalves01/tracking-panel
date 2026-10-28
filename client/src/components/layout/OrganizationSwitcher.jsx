import { useState } from 'react'
import { Building2, ChevronsUpDown, Fuel } from 'lucide-react'
import { useNavigate } from 'react-router'

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { formatCpfCnpj } from '@/helpers/formatters'
import { selectOrganization } from '@/lib/auth/auth'
import { useSession } from '@/lib/auth/session'
import { notifyError } from '@/lib/notify'

const typeIcon = {
    company: Building2,
    fuel_station: Fuel,
}

/**
 * A organização atual, e a troca para outra.
 *
 * Antes empresas e postos eram dois grupos separados no menu, cada um com
 * sua rota de troca. Agora é uma lista só — a escolha é a mesma, e o selo
 * diz o tipo.
 */
export function OrganizationSwitcher() {
    const navigate = useNavigate()
    const { current_organization: current, organizations = [], invitations = [] } = useSession()
    const [switching, setSwitching] = useState(false)

    if (!current) {
        return null
    }

    const Icon = typeIcon[current.type] ?? Building2
    const pending = invitations.length

    async function switchTo(uuid) {
        if (uuid === current.uuid) return

        setSwitching(true)

        try {
            const response = await selectOrganization(uuid)
            navigate(response.redirect_to)
        } catch (error) {
            notifyError(error)
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
                        className="bg-background hover:bg-muted flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm transition-colors disabled:pointer-events-none disabled:opacity-50"
                    >
                        <Icon className="text-muted-foreground size-4 shrink-0" />
                        <span className="font-medium">{current.display_name}</span>
                        <span className="text-muted-foreground tabular-nums">
                            {formatCpfCnpj(current.document)}
                        </span>
                        <CurrentDot />
                        <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
                    </button>
                }
            />
            <DropdownMenuContent align="end" className="w-72">
                {/* Todas, inclusive a atual: a lista é o mapa de onde a pessoa
                    pode estar, e a bolinha diz onde ela está. */}
                {organizations.map((organization) => {
                    const ItemIcon = typeIcon[organization.type] ?? Building2
                    const isCurrent = organization.uuid === current.uuid

                    return (
                        <DropdownMenuItem
                            key={organization.uuid}
                            aria-current={isCurrent ? 'true' : undefined}
                            onClick={() => switchTo(organization.uuid)}
                        >
                            <span className="flex min-w-0 flex-1 items-start gap-2">
                                <ItemIcon className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                <span className="flex min-w-0 flex-col">
                                    <span className="truncate font-medium">
                                        {organization.display_name}
                                    </span>
                                    <span className="text-muted-foreground truncate text-xs tabular-nums">
                                        {organization.type_label} ·{' '}
                                        {formatCpfCnpj(organization.document)}
                                    </span>
                                </span>
                            </span>
                            {isCurrent ? <CurrentDot /> : null}
                        </DropdownMenuItem>
                    )
                })}

                <DropdownMenuSeparator />

                {/* Cadastrar um posto saiu daqui: este menu é para trocar, o
                    que se faz várias vezes por dia. A tela de organizações,
                    a um clique, mantém essa porta. */}
                <DropdownMenuItem className="justify-center" onClick={() => navigate('/auth/organizacoes')}>
                    Ver todas
                    {pending > 0 ? ` · ${pending} convite${pending > 1 ? 's' : ''}` : ''}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    )
}

/** A organização em que a sessão está agora. */
function CurrentDot() {
    return (
        <span className="relative flex size-2 shrink-0" title="Organização atual">
            <span className="absolute inline-flex size-full rounded-full bg-emerald-500 opacity-75 motion-safe:animate-ping" />
            <span className="relative inline-flex size-2 rounded-full bg-emerald-500" />
            <span className="sr-only">Organização atual</span>
        </span>
    )
}
