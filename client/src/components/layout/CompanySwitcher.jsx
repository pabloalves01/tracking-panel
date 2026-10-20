import { useState } from 'react'
import { Building2, ChevronsUpDown } from 'lucide-react'

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { formatCpfCnpj } from '@/helpers/formatters'
import { useSession } from '@/lib/auth/session'

/**
 * Pílula com a empresa atual. Ao abrir, lista as empresas do usuário
 * e troca de empresa na mesma sessão.
 */
export function CompanySwitcher() {
    const { currentCompany, companies, switchCompany } = useSession()
    const [switching, setSwitching] = useState(false)

    async function handleSelect(uuid) {
        setSwitching(true)
        try {
            await switchCompany(uuid)
        } catch {
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
                        <Building2 className="size-4 shrink-0 text-muted-foreground" />
                        <span className="font-medium">{currentCompany?.trade_name}</span>
                        <span className="text-muted-foreground">
                            {formatCpfCnpj(currentCompany?.cnpj)}
                        </span>
                        <ChevronsUpDown className="size-4 shrink-0 text-muted-foreground" />
                    </button>
                }
            />
            <DropdownMenuContent align="end" className="w-72">
                <DropdownMenuRadioGroup
                    value={currentCompany?.uuid}
                    onValueChange={handleSelect}
                >
                    {/* <DropdownMenuLabel>Trocar empresa</DropdownMenuLabel> */}
                    {companies.map((company) => (
                        <DropdownMenuRadioItem key={company.uuid} value={company.uuid}>
                            <span className="flex min-w-0 flex-col">
                                <span className="truncate font-medium">{company.trade_name}</span>
                                {/* <span className="truncate text-xs text-muted-foreground">
                                    {company.legal_name}
                                </span> */}
                                <span className="text-xs text-muted-foreground">
                                    {formatCpfCnpj(company.cnpj)}
                                </span>
                            </span>
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    )
}
