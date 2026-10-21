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
import { activateDriver, deactivateDriver, getDrivers } from '@/lib/registry/drivers'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/motoristas'

const personTypeLabel = {
    pf: 'Pessoa física',
    pj: 'Pessoa jurídica',
}

export default function DriversPage() {
    const navigate = useNavigate()
    const [drivers, setDrivers] = useState([])
    const [loading, setLoading] = useState(false)
    const [driverToToggle, setDriverToToggle] = useState(null)

    useEffect(() => {
        loadDrivers()
    }, [])

    function loadDrivers() {
        setLoading(true)
        getDrivers()
            .then(setDrivers)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    const driverIsActive = driverToToggle?.status !== 'inactive'

    async function handleToggleStatus() {
        if (!driverToToggle) return
        setLoading(true)

        try {
            const response = driverIsActive
                ? await deactivateDriver(driverToToggle.uuid)
                : await activateDriver(driverToToggle.uuid)
            notifySuccess(response)
            loadDrivers()
        } catch (error) {
            notifyError(error)
        } finally {
            setDriverToToggle(null)
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
                    { text: 'Motoristas' },
                ]}
                buttons={[
                    { title: 'Novo motorista', icon: PlusIcon, to: `${listPath}/novo` },
                ]}
            />

            <LoadingWrapper loading={loading}>
                {!loading && drivers.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <UsersIcon />
                            </EmptyMedia>
                            <EmptyTitle>Nenhum motorista</EmptyTitle>
                            <EmptyDescription>Cadastre o primeiro motorista para começar.</EmptyDescription>
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
                            {drivers.map((driver) => {
                                const isActive = driver.status !== 'inactive'

                                return (
                                    <TableRow key={driver.uuid}>
                                        <TableCell>{driver.name}</TableCell>
                                        <TableCell>{driver.trade_name}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {personTypeLabel[driver.person_type] ?? driver.person_type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{formatCpfCnpj(driver.cpf_cnpj)}</TableCell>
                                        <TableCell>{driver.email}</TableCell>
                                        <TableCell>{formatPhone(driver.phone)}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{isActive ? 'Ativo' : 'Inativo'}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <ActionButton
                                                    icon={PencilIcon}
                                                    tooltip="Editar"
                                                    onClick={() => navigate(`${listPath}/${driver.uuid}/editar`)}
                                                />
                                                <ActionButton
                                                    icon={isActive ? BanIcon : CircleCheckIcon}
                                                    tooltip={isActive ? 'Desativar' : 'Ativar'}
                                                    variant={isActive ? 'destructive' : 'outline'}
                                                    onClick={() => setDriverToToggle(driver)}
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

            <AlertDialog open={!!driverToToggle} onOpenChange={(open) => !open && setDriverToToggle(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {driverIsActive ? 'Desativar motorista' : 'Ativar motorista'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {driverIsActive
                                ? `Tem certeza que deseja desativar ${driverToToggle?.name}? O cadastro permanece e pode ser ativado de novo.`
                                : `Tem certeza que deseja ativar ${driverToToggle?.name}?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            variant={driverIsActive ? 'destructive' : 'default'}
                            onClick={handleToggleStatus}
                        >
                            {driverIsActive ? 'Desativar' : 'Ativar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
