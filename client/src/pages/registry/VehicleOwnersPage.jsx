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
import { activateVehicleOwner, deactivateVehicleOwner, getVehicleOwners } from '@/lib/registry/vehicleOwners'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/proprietarios'

const personTypeLabel = {
    pf: 'Pessoa física',
    pj: 'Pessoa jurídica',
}

export default function VehicleOwnersPage() {
    const navigate = useNavigate()
    const [vehicleOwners, setVehicleOwners] = useState([])
    const [loading, setLoading] = useState(false)
    const [vehicleOwnerToToggle, setVehicleOwnerToToggle] = useState(null)

    useEffect(() => {
        loadVehicleOwners()
    }, [])

    function loadVehicleOwners() {
        setLoading(true)
        getVehicleOwners()
            .then(setVehicleOwners)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    const vehicleOwnerIsActive = vehicleOwnerToToggle?.status !== 'inactive'

    async function handleToggleStatus() {
        if (!vehicleOwnerToToggle) return
        setLoading(true)

        try {
            const response = vehicleOwnerIsActive
                ? await deactivateVehicleOwner(vehicleOwnerToToggle.uuid)
                : await activateVehicleOwner(vehicleOwnerToToggle.uuid)
            notifySuccess(response)
            loadVehicleOwners()
        } catch (error) {
            notifyError(error)
        } finally {
            setVehicleOwnerToToggle(null)
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
                    { text: 'Proprietários' },
                ]}
                buttons={[
                    { title: 'Novo proprietário', icon: PlusIcon, to: `${listPath}/novo` },
                ]}
            />

            <LoadingWrapper loading={loading}>
                {!loading && vehicleOwners.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <UsersIcon />
                            </EmptyMedia>
                            <EmptyTitle>Nenhum proprietário</EmptyTitle>
                            <EmptyDescription>Cadastre o primeiro proprietário para começar.</EmptyDescription>
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
                            {vehicleOwners.map((vehicleOwner) => {
                                const isActive = vehicleOwner.status !== 'inactive'

                                return (
                                    <TableRow key={vehicleOwner.uuid}>
                                        <TableCell>{vehicleOwner.name}</TableCell>
                                        <TableCell>{vehicleOwner.trade_name}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {personTypeLabel[vehicleOwner.person_type] ?? vehicleOwner.person_type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{formatCpfCnpj(vehicleOwner.cpf_cnpj)}</TableCell>
                                        <TableCell>{vehicleOwner.email}</TableCell>
                                        <TableCell>{formatPhone(vehicleOwner.phone)}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{isActive ? 'Ativo' : 'Inativo'}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <ActionButton
                                                    icon={PencilIcon}
                                                    tooltip="Editar"
                                                    onClick={() => navigate(`${listPath}/${vehicleOwner.uuid}/editar`)}
                                                />
                                                <ActionButton
                                                    icon={isActive ? BanIcon : CircleCheckIcon}
                                                    tooltip={isActive ? 'Desativar' : 'Ativar'}
                                                    variant={isActive ? 'destructive' : 'outline'}
                                                    onClick={() => setVehicleOwnerToToggle(vehicleOwner)}
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

            <AlertDialog open={!!vehicleOwnerToToggle} onOpenChange={(open) => !open && setVehicleOwnerToToggle(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {vehicleOwnerIsActive ? 'Desativar proprietário' : 'Ativar proprietário'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {vehicleOwnerIsActive
                                ? `Tem certeza que deseja desativar ${vehicleOwnerToToggle?.name}? O cadastro permanece e pode ser ativado de novo.`
                                : `Tem certeza que deseja ativar ${vehicleOwnerToToggle?.name}?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            variant={vehicleOwnerIsActive ? 'destructive' : 'default'}
                            onClick={handleToggleStatus}
                        >
                            {vehicleOwnerIsActive ? 'Desativar' : 'Ativar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
