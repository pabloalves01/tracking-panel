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
import { activateVehicleResponsible, deactivateVehicleResponsible, getVehicleResponsibles } from '@/lib/registry/vehicleResponsibles'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/responsaveis'

const personTypeLabel = {
    pf: 'Pessoa física',
    pj: 'Pessoa jurídica',
}

export default function VehicleResponsiblesPage() {
    const navigate = useNavigate()
    const [vehicleResponsibles, setVehicleResponsibles] = useState([])
    const [loading, setLoading] = useState(false)
    const [vehicleResponsibleToToggle, setVehicleResponsibleToToggle] = useState(null)

    useEffect(() => {
        loadVehicleResponsibles()
    }, [])

    function loadVehicleResponsibles() {
        setLoading(true)
        getVehicleResponsibles()
            .then(setVehicleResponsibles)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    const vehicleResponsibleIsActive = vehicleResponsibleToToggle?.status !== 'inactive'

    async function handleToggleStatus() {
        if (!vehicleResponsibleToToggle) return
        setLoading(true)

        try {
            const response = vehicleResponsibleIsActive
                ? await deactivateVehicleResponsible(vehicleResponsibleToToggle.uuid)
                : await activateVehicleResponsible(vehicleResponsibleToToggle.uuid)
            notifySuccess(response)
            loadVehicleResponsibles()
        } catch (error) {
            notifyError(error)
        } finally {
            setVehicleResponsibleToToggle(null)
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
                    { text: 'Responsáveis' },
                ]}
                buttons={[
                    { title: 'Novo responsável', icon: PlusIcon, to: `${listPath}/novo`, permission: 'write' },
                ]}
            />

            <LoadingWrapper loading={loading}>
                {!loading && vehicleResponsibles.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <UsersIcon />
                            </EmptyMedia>
                            <EmptyTitle>Nenhum responsável</EmptyTitle>
                            <EmptyDescription>Cadastre o primeiro responsável para começar.</EmptyDescription>
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
                            {vehicleResponsibles.map((vehicleResponsible) => {
                                const isActive = vehicleResponsible.status !== 'inactive'

                                return (
                                    <TableRow key={vehicleResponsible.uuid}>
                                        <TableCell>{vehicleResponsible.name}</TableCell>
                                        <TableCell>{vehicleResponsible.trade_name}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {personTypeLabel[vehicleResponsible.person_type] ?? vehicleResponsible.person_type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{formatCpfCnpj(vehicleResponsible.cpf_cnpj)}</TableCell>
                                        <TableCell>{vehicleResponsible.email}</TableCell>
                                        <TableCell>{formatPhone(vehicleResponsible.phone)}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{isActive ? 'Ativo' : 'Inativo'}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <ActionButton
                                                    icon={PencilIcon}
                                                    tooltip="Editar"
                                                    onClick={() => navigate(`${listPath}/${vehicleResponsible.uuid}/editar`)}
                                                />
                                                <ActionButton
                                                    icon={isActive ? BanIcon : CircleCheckIcon}
                                                    tooltip={isActive ? 'Desativar' : 'Ativar'}
                                                    variant={isActive ? 'destructive' : 'outline'}
                                                    onClick={() => setVehicleResponsibleToToggle(vehicleResponsible)}
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

            <AlertDialog open={!!vehicleResponsibleToToggle} onOpenChange={(open) => !open && setVehicleResponsibleToToggle(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {vehicleResponsibleIsActive ? 'Desativar responsável' : 'Ativar responsável'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {vehicleResponsibleIsActive
                                ? `Tem certeza que deseja desativar ${vehicleResponsibleToToggle?.name}? O cadastro permanece e pode ser ativado de novo.`
                                : `Tem certeza que deseja ativar ${vehicleResponsibleToToggle?.name}?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            variant={vehicleResponsibleIsActive ? 'destructive' : 'default'}
                            onClick={handleToggleStatus}
                        >
                            {vehicleResponsibleIsActive ? 'Desativar' : 'Ativar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
