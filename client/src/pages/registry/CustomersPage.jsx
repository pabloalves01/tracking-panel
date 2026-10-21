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
import { activateCustomer, deactivateCustomer, getCustomers } from '@/lib/registry/customers'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/clientes'

const personTypeLabel = {
    pf: 'Pessoa física',
    pj: 'Pessoa jurídica',
}

export default function CustomersPage() {
    const navigate = useNavigate()
    const [customers, setCustomers] = useState([])
    const [loading, setLoading] = useState(false)
    const [customerToToggle, setCustomerToToggle] = useState(null)

    useEffect(() => {
        loadCustomers()
    }, [])

    function loadCustomers() {
        setLoading(true)
        getCustomers()
            .then(setCustomers)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    const customerIsActive = customerToToggle?.status !== 'inactive'

    async function handleToggleStatus() {
        if (!customerToToggle) return
        setLoading(true)

        try {
            const response = customerIsActive
                ? await deactivateCustomer(customerToToggle.uuid)
                : await activateCustomer(customerToToggle.uuid)
            notifySuccess(response)
            loadCustomers()
        } catch (error) {
            notifyError(error)
        } finally {
            setCustomerToToggle(null)
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
                    { text: 'Clientes' },
                ]}
                buttons={[
                    { title: 'Novo cliente', icon: PlusIcon, to: `${listPath}/novo` },
                ]}
            />

            <LoadingWrapper loading={loading}>
                {!loading && customers.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <UsersIcon />
                            </EmptyMedia>
                            <EmptyTitle>Nenhum cliente</EmptyTitle>
                            <EmptyDescription>Cadastre o primeiro cliente para começar.</EmptyDescription>
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
                            {customers.map((customer) => {
                                const isActive = customer.status !== 'inactive'

                                return (
                                    <TableRow key={customer.uuid}>
                                        <TableCell>{customer.name}</TableCell>
                                        <TableCell>{customer.trade_name}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {personTypeLabel[customer.person_type] ?? customer.person_type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{formatCpfCnpj(customer.cpf_cnpj)}</TableCell>
                                        <TableCell>{customer.email}</TableCell>
                                        <TableCell>{formatPhone(customer.phone)}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{isActive ? 'Ativo' : 'Inativo'}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <ActionButton
                                                    icon={PencilIcon}
                                                    tooltip="Editar"
                                                    onClick={() => navigate(`${listPath}/${customer.uuid}/editar`)}
                                                />
                                                <ActionButton
                                                    icon={isActive ? BanIcon : CircleCheckIcon}
                                                    tooltip={isActive ? 'Desativar' : 'Ativar'}
                                                    variant={isActive ? 'destructive' : 'outline'}
                                                    onClick={() => setCustomerToToggle(customer)}
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

            <AlertDialog open={!!customerToToggle} onOpenChange={(open) => !open && setCustomerToToggle(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {customerIsActive ? 'Desativar cliente' : 'Ativar cliente'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {customerIsActive
                                ? `Tem certeza que deseja desativar ${customerToToggle?.name}? O cadastro permanece e pode ser ativado de novo.`
                                : `Tem certeza que deseja ativar ${customerToToggle?.name}?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            variant={customerIsActive ? 'destructive' : 'default'}
                            onClick={handleToggleStatus}
                        >
                            {customerIsActive ? 'Desativar' : 'Ativar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
