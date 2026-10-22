import { useEffect, useState } from 'react'
import { Fuel, PlusIcon, Trash2Icon } from 'lucide-react'

import { ActionButton } from '@/components/common/button/ActionButton'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { Badge } from '@/components/ui/badge'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatCpfCnpj } from '@/helpers/formatters'
import { deleteCompanyFuelStation, getCompanyFuelStations } from '@/lib/registry/fuelStations'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/postos'

const statusLabel = {
    pending: 'Pendente',
    accepted: 'Aceito',
    rejected: 'Recusado',
}

export default function FuelStationsPage() {
    const [links, setLinks] = useState([])
    const [loading, setLoading] = useState(false)

    useEffect(() => {
        loadLinks()
    }, [])

    function loadLinks() {
        setLoading(true)
        getCompanyFuelStations()
            .then(setLinks)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    async function handleRemove(link) {
        setLoading(true)

        try {
            const response = await deleteCompanyFuelStation(link.cnpj)
            notifySuccess(response)
            loadLinks()
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Entidades', to: '/app/cadastros/entidades' },
                    { text: 'Postos' },
                ]}
                buttons={[
                    { title: 'Novo posto', icon: PlusIcon, to: `${listPath}/novo` },
                ]}
            />

            <LoadingWrapper loading={loading}>
                {!loading && links.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Fuel />
                            </EmptyMedia>
                            <EmptyTitle>Nenhum posto</EmptyTitle>
                            <EmptyDescription>Peça o vínculo do primeiro posto para começar.</EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Posto</TableHead>
                                <TableHead>CNPJ</TableHead>
                                <TableHead>Situação</TableHead>
                                <TableHead>Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {links.map((link) => (
                                <TableRow key={link.cnpj}>
                                    <TableCell>
                                        {link.fuel_station?.trade_name
                                            || link.fuel_station?.corporate_name
                                            || 'Posto excluído'}
                                    </TableCell>
                                    <TableCell>{formatCpfCnpj(link.cnpj)}</TableCell>
                                    <TableCell>
                                        <Badge variant={link.status === 'accepted' ? 'secondary' : 'outline'}>
                                            {statusLabel[link.status] ?? link.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <ActionButton
                                                icon={Trash2Icon}
                                                tooltip="Remover"
                                                variant="destructive"
                                                onClick={() => handleRemove(link)}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </LoadingWrapper>
        </>
    )
}
