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
import { activateCarrier, deactivateCarrier, getCarriers } from '@/lib/registry/carriers'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/transportadoras'

const personTypeLabel = {
    pf: 'Pessoa física',
    pj: 'Pessoa jurídica',
}

export default function CarriersPage() {
    const navigate = useNavigate()
    const [carriers, setCarriers] = useState([])
    const [loading, setLoading] = useState(false)
    const [carrierToToggle, setCarrierToToggle] = useState(null)

    useEffect(() => {
        loadCarriers()
    }, [])

    function loadCarriers() {
        setLoading(true)
        getCarriers()
            .then(setCarriers)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    const carrierIsActive = carrierToToggle?.status !== 'inactive'

    async function handleToggleStatus() {
        if (!carrierToToggle) return
        setLoading(true)

        try {
            const response = carrierIsActive
                ? await deactivateCarrier(carrierToToggle.uuid)
                : await activateCarrier(carrierToToggle.uuid)
            notifySuccess(response)
            loadCarriers()
        } catch (error) {
            notifyError(error)
        } finally {
            setCarrierToToggle(null)
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
                    { text: 'Transportadoras' },
                ]}
                buttons={[
                    { title: 'Nova transportadora', icon: PlusIcon, to: `${listPath}/novo`, permission: 'write' },
                ]}
            />

            <LoadingWrapper loading={loading}>
                {!loading && carriers.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <UsersIcon />
                            </EmptyMedia>
                            <EmptyTitle>Nenhuma transportadora</EmptyTitle>
                            <EmptyDescription>Cadastre a primeira transportadora para começar.</EmptyDescription>
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
                            {carriers.map((carrier) => {
                                const isActive = carrier.status !== 'inactive'

                                return (
                                    <TableRow key={carrier.uuid}>
                                        <TableCell>{carrier.name}</TableCell>
                                        <TableCell>{carrier.trade_name}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {personTypeLabel[carrier.person_type] ?? carrier.person_type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{formatCpfCnpj(carrier.cpf_cnpj)}</TableCell>
                                        <TableCell>{carrier.email}</TableCell>
                                        <TableCell>{formatPhone(carrier.phone)}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{isActive ? 'Ativo' : 'Inativo'}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <ActionButton
                                                    icon={PencilIcon}
                                                    tooltip="Editar"
                                                    onClick={() => navigate(`${listPath}/${carrier.uuid}/editar`)}
                                                />
                                                <ActionButton
                                                    icon={isActive ? BanIcon : CircleCheckIcon}
                                                    tooltip={isActive ? 'Desativar' : 'Ativar'}
                                                    variant={isActive ? 'destructive' : 'outline'}
                                                    onClick={() => setCarrierToToggle(carrier)}
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

            <AlertDialog open={!!carrierToToggle} onOpenChange={(open) => !open && setCarrierToToggle(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {carrierIsActive ? 'Desativar transportadora' : 'Ativar transportadora'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {carrierIsActive
                                ? `Tem certeza que deseja desativar ${carrierToToggle?.name}? O cadastro permanece e pode ser ativado de novo.`
                                : `Tem certeza que deseja ativar ${carrierToToggle?.name}?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            variant={carrierIsActive ? 'destructive' : 'default'}
                            onClick={handleToggleStatus}
                        >
                            {carrierIsActive ? 'Desativar' : 'Ativar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
