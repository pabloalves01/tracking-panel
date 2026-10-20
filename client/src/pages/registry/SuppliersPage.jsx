import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router'
import { PencilIcon, PlusIcon, TrashIcon, UsersIcon } from 'lucide-react'

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
import { deleteSupplier, getSuppliers } from '@/lib/registry/suppliers'
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
    const [supplierToDelete, setSupplierToDelete] = useState(null)

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

    async function handleDelete() {
        if (!supplierToDelete) return
        setLoading(true)

        try {
            const response = await deleteSupplier(supplierToDelete.uuid)
            notifySuccess(response)
            loadSuppliers()
        } catch (error) {
            notifyError(error)
        } finally {
            setSupplierToDelete(null)
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
                                <TableHead>Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {suppliers.map((supplier) => (
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
                                        <div className="flex items-center gap-2">
                                            <ActionButton
                                                icon={PencilIcon}
                                                tooltip="Editar"
                                                onClick={() => navigate(`${listPath}/${supplier.uuid}/editar`)}
                                            />
                                            <ActionButton
                                                icon={TrashIcon}
                                                tooltip="Excluir"
                                                variant="destructive"
                                                onClick={() => setSupplierToDelete(supplier)}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </LoadingWrapper>

            <AlertDialog open={!!supplierToDelete} onOpenChange={(open) => !open && setSupplierToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Excluir fornecedor</AlertDialogTitle>
                        <AlertDialogDescription>
                            Tem certeza que deseja excluir {supplierToDelete?.name}? Essa ação não pode ser desfeita.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={handleDelete}>
                            Excluir
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
