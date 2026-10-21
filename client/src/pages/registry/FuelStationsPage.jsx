import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router'
import { BanIcon, CircleCheckIcon, PencilIcon, PlusIcon, UsersIcon } from 'lucide-react'

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
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatCpfCnpj, formatPhone } from '@/helpers/formatters'
import { activateFuelStation, deactivateFuelStation, getFuelStations } from '@/lib/registry/fuelStations'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/postos'

const personTypeLabel = {
    pf: 'Pessoa física',
    pj: 'Pessoa jurídica',
}

const statusLabel = {
    active: 'Ativo',
    inactive: 'Inativo',
    suspended: 'Suspenso',
}

export default function FuelStationsPage() {
    const navigate = useNavigate()
    const [fuelStations, setFuelStations] = useState([])
    const [loading, setLoading] = useState(false)
    const [fuelStationToToggle, setFuelStationToToggle] = useState(null)

    useEffect(() => {
        loadFuelStations()
    }, [])

    function loadFuelStations() {
        setLoading(true)
        getFuelStations()
            .then(setFuelStations)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    const fuelStationIsActive = fuelStationToToggle?.status !== 'inactive'

    async function handleToggleStatus() {
        if (!fuelStationToToggle) return
        setLoading(true)

        try {
            const response = fuelStationIsActive
                ? await deactivateFuelStation(fuelStationToToggle.uuid)
                : await activateFuelStation(fuelStationToToggle.uuid)
            notifySuccess(response)
            loadFuelStations()
        } catch (error) {
            notifyError(error)
        } finally {
            setFuelStationToToggle(null)
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
                {!loading && fuelStations.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <UsersIcon />
                            </EmptyMedia>
                            <EmptyTitle>Nenhum posto</EmptyTitle>
                            <EmptyDescription>Cadastre o primeiro posto para começar.</EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nome</TableHead>
                                <TableHead>Nome fantasia</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Documento</TableHead>
                                <TableHead>E-mail</TableHead>
                                <TableHead>Telefone</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {fuelStations.map((fuelStation) => {
                                const isActive = fuelStation.status !== 'inactive'

                                return (
                                    <TableRow key={fuelStation.uuid}>
                                        <TableCell>{fuelStation.name}</TableCell>
                                        <TableCell>{fuelStation.trade_name}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {personTypeLabel[fuelStation.person_type] ?? fuelStation.person_type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{formatCpfCnpj(fuelStation.cpf_cnpj)}</TableCell>
                                        <TableCell>{fuelStation.email}</TableCell>
                                        <TableCell>{formatPhone(fuelStation.phone)}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{statusLabel[fuelStation.status] ?? fuelStation.status}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <ActionButton
                                                    icon={PencilIcon}
                                                    tooltip="Editar"
                                                    onClick={() => navigate(`${listPath}/${fuelStation.uuid}/editar`)}
                                                />
                                                <ActionButton
                                                    icon={isActive ? BanIcon : CircleCheckIcon}
                                                    tooltip={isActive ? 'Desativar' : 'Ativar'}
                                                    variant={isActive ? 'destructive' : 'outline'}
                                                    onClick={() => setFuelStationToToggle(fuelStation)}
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                )
                            })}
                        </TableBody>
                    </Table>
                )}
            </LoadingWrapper>

            <AlertDialog open={!!fuelStationToToggle} onOpenChange={(open) => !open && setFuelStationToToggle(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {fuelStationIsActive ? 'Desativar posto' : 'Ativar posto'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {fuelStationIsActive
                                ? `Tem certeza que deseja desativar ${fuelStationToToggle?.name}? O cadastro permanece e pode ser ativado de novo.`
                                : `Tem certeza que deseja ativar ${fuelStationToToggle?.name}?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            variant={fuelStationIsActive ? 'destructive' : 'default'}
                            onClick={handleToggleStatus}
                        >
                            {fuelStationIsActive ? 'Desativar' : 'Ativar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
