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
import { activateSupplier, deactivateSupplier, getSuppliers } from '@/lib/registry/suppliers'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/fornecedores'

const personTypeLabel = {
    pf: 'Pessoa física',
    pj: 'Pessoa jurídica',
}

export default function SuppliersPage() {
    const navigate = useNavigate()
    const [suppliers, setSuppliers] = useState([])
    const [loading, setLoading] = useState(false)
    const [supplierToToggle, setSupplierToToggle] = useState(null)

    useEffect(() => {
        loadSuppliers()
    }, [])

    function loadSuppliers() {
        setLoading(true)
        getSuppliers()
            .then(setSuppliers)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    const supplierIsActive = supplierToToggle?.status !== 'inactive'

    async function handleToggleStatus() {
        if (!supplierToToggle) return
        setLoading(true)

        try {
            const response = supplierIsActive
                ? await deactivateSupplier(supplierToToggle.uuid)
                : await activateSupplier(supplierToToggle.uuid)
            notifySuccess(response)
            loadSuppliers()
        } catch (error) {
            notifyError(error)
        } finally {
            setSupplierToToggle(null)
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
                    { text: 'Fornecedores' },
                ]}
                buttons={[
                    { title: 'Novo fornecedor', icon: PlusIcon, to: `${listPath}/novo` },
                ]}
            />

            <LoadingWrapper loading={loading}>
                {!loading && suppliers.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <UsersIcon />
                            </EmptyMedia>
                            <EmptyTitle>Nenhum fornecedor</EmptyTitle>
                            <EmptyDescription>Cadastre o primeiro fornecedor para começar.</EmptyDescription>
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
                            {suppliers.map((supplier) => {
                                const isActive = supplier.status !== 'inactive'

                                return (
                                    <TableRow key={supplier.uuid}>
                                        <TableCell>{supplier.name}</TableCell>
                                        <TableCell>{supplier.trade_name}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {personTypeLabel[supplier.person_type] ?? supplier.person_type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{formatCpfCnpj(supplier.cpf_cnpj)}</TableCell>
                                        <TableCell>{supplier.email}</TableCell>
                                        <TableCell>{formatPhone(supplier.phone)}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{isActive ? 'Ativo' : 'Inativo'}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <ActionButton
                                                    icon={PencilIcon}
                                                    tooltip="Editar"
                                                    onClick={() => navigate(`${listPath}/${supplier.uuid}/editar`)}
                                                />
                                                <ActionButton
                                                    icon={isActive ? BanIcon : CircleCheckIcon}
                                                    tooltip={isActive ? 'Desativar' : 'Ativar'}
                                                    variant={isActive ? 'destructive' : 'outline'}
                                                    onClick={() => setSupplierToToggle(supplier)}
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

            <AlertDialog open={!!supplierToToggle} onOpenChange={(open) => !open && setSupplierToToggle(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {supplierIsActive ? 'Desativar fornecedor' : 'Ativar fornecedor'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {supplierIsActive
                                ? `Tem certeza que deseja desativar ${supplierToToggle?.name}? O cadastro permanece e pode ser ativado de novo.`
                                : `Tem certeza que deseja ativar ${supplierToToggle?.name}?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            variant={supplierIsActive ? 'destructive' : 'default'}
                            onClick={handleToggleStatus}
                        >
                            {supplierIsActive ? 'Desativar' : 'Ativar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
