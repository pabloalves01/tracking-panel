import { useEffect, useState } from 'react'
import { Fuel, Link2Off, PlusIcon, RefreshCw, Trash2Icon } from 'lucide-react'

import { ActionButton } from '@/components/common/button/ActionButton'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { linkStatusLabel, statusBadgeClass } from '@/helpers/labels/linkStatusLabel'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatCpfCnpj } from '@/helpers/formatters'
import {
    cancelFuelStationAgreement,
    disconnectFuelStationAgreement,
    getFuelStationAgreements,
    requestFuelStationAgreement,
} from '@/lib/registry/fuelStations'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/postos'

function fuelStationLabel(link) {
    return link?.fuel_station?.display_name
        || 'Posto excluído'
}

export default function FuelStationsPage() {
    const [links, setLinks] = useState([])
    const [loading, setLoading] = useState(false)
    const [linkToRemove, setLinkToRemove] = useState(null)

    useEffect(() => {
        loadLinks()
    }, [])

    function loadLinks() {
        setLoading(true)
        getFuelStationAgreements()
            .then(setLinks)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    async function handleRemove() {
        if (!linkToRemove) {
            return
        }
        setLoading(true)

        try {
            // O uuid é o do vínculo, não o do posto: é o vínculo que tem
            // estado, e é dele que a ação fala.
            const response = linkToRemove.status === 'active'
                ? await disconnectFuelStationAgreement(linkToRemove.uuid)
                : await cancelFuelStationAgreement(linkToRemove.uuid)
            notifySuccess(response)
            loadLinks()
        } catch (error) {
            notifyError(error)
        } finally {
            setLinkToRemove(null)
            setLoading(false)
        }
    }

    async function handleResendRequest(link) {
        setLoading(true)

        try {
            const response = await requestFuelStationAgreement(link.fuel_station.document)
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
                    { title: 'Novo posto', icon: PlusIcon, to: `${listPath}/novo`, permission: 'write' },
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
                                <TableRow key={link.uuid}>
                                    <TableCell>{fuelStationLabel(link)}</TableCell>
                                    <TableCell>{formatCpfCnpj(link.fuel_station?.document)}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline" className={statusBadgeClass(link.status)}>
                                            {linkStatusLabel[link.status] ?? link.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            {link.status === 'request_sent' && link.fuel_station?.uuid ? (
                                                <ActionButton
                                                    icon={Trash2Icon}
                                                    tooltip="Remover pedido"
                                                    variant="destructive"
                                                    onClick={() => setLinkToRemove(link)}
                                                />
                                            ) : null}
                                            {link.status === 'active' && link.fuel_station?.uuid ? (
                                                <ActionButton
                                                    icon={Link2Off}
                                                    tooltip="Encerrar conexão"
                                                    variant="destructive"
                                                    onClick={() => setLinkToRemove(link)}
                                                />
                                            ) : null}
                                            {(link.status === 'rejected' || link.status === 'disconnected') && link.fuel_station?.uuid ? (
                                                <ActionButton
                                                    icon={RefreshCw}
                                                    tooltip="Reenviar convite"
                                                    variant="outline"
                                                    onClick={() => handleResendRequest(link)}
                                                />
                                            ) : null}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </LoadingWrapper>

            <AlertDialog open={!!linkToRemove} onOpenChange={(open) => !open && setLinkToRemove(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {linkToRemove?.status === 'active' ? 'Encerrar conexão' : 'Remover pedido'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {linkToRemove?.status === 'active'
                                ? `Tem certeza que deseja encerrar a conexão com ${fuelStationLabel(linkToRemove)}?`
                                : `Tem certeza que deseja remover o pedido de ${fuelStationLabel(linkToRemove)}?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={handleRemove}>
                            {linkToRemove?.status === 'active' ? 'Encerrar conexão' : 'Remover pedido'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
