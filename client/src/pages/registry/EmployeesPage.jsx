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
import { activateEmployee, deactivateEmployee, getEmployees } from '@/lib/registry/employees'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/funcionarios'

const personTypeLabel = {
    pf: 'Pessoa física',
    pj: 'Pessoa jurídica',
}

export default function EmployeesPage() {
    const navigate = useNavigate()
    const [employees, setEmployees] = useState([])
    const [loading, setLoading] = useState(false)
    const [employeeToToggle, setEmployeeToToggle] = useState(null)

    useEffect(() => {
        loadEmployees()
    }, [])

    function loadEmployees() {
        setLoading(true)
        getEmployees()
            .then(setEmployees)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    const employeeIsActive = employeeToToggle?.status !== 'inactive'

    async function handleToggleStatus() {
        if (!employeeToToggle) return
        setLoading(true)

        try {
            const response = employeeIsActive
                ? await deactivateEmployee(employeeToToggle.uuid)
                : await activateEmployee(employeeToToggle.uuid)
            notifySuccess(response)
            loadEmployees()
        } catch (error) {
            notifyError(error)
        } finally {
            setEmployeeToToggle(null)
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
                    { text: 'Funcionários' },
                ]}
                buttons={[
                    { title: 'Novo funcionário', icon: PlusIcon, to: `${listPath}/novo`, permission: 'write' },
                ]}
            />

            <LoadingWrapper loading={loading}>
                {!loading && employees.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <UsersIcon />
                            </EmptyMedia>
                            <EmptyTitle>Nenhum funcionário</EmptyTitle>
                            <EmptyDescription>Cadastre o primeiro funcionário para começar.</EmptyDescription>
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
                            {employees.map((employee) => {
                                const isActive = employee.status !== 'inactive'

                                return (
                                    <TableRow key={employee.uuid}>
                                        <TableCell>{employee.name}</TableCell>
                                        <TableCell>{employee.trade_name}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {personTypeLabel[employee.person_type] ?? employee.person_type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{formatCpfCnpj(employee.cpf_cnpj)}</TableCell>
                                        <TableCell>{employee.email}</TableCell>
                                        <TableCell>{formatPhone(employee.phone)}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{isActive ? 'Ativo' : 'Inativo'}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <ActionButton
                                                    icon={PencilIcon}
                                                    tooltip="Editar"
                                                    onClick={() => navigate(`${listPath}/${employee.uuid}/editar`)}
                                                />
                                                <ActionButton
                                                    icon={isActive ? BanIcon : CircleCheckIcon}
                                                    tooltip={isActive ? 'Desativar' : 'Ativar'}
                                                    variant={isActive ? 'destructive' : 'outline'}
                                                    onClick={() => setEmployeeToToggle(employee)}
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

            <AlertDialog open={!!employeeToToggle} onOpenChange={(open) => !open && setEmployeeToToggle(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {employeeIsActive ? 'Desativar funcionário' : 'Ativar funcionário'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {employeeIsActive
                                ? `Tem certeza que deseja desativar ${employeeToToggle?.name}? O cadastro permanece e pode ser ativado de novo.`
                                : `Tem certeza que deseja ativar ${employeeToToggle?.name}?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            variant={employeeIsActive ? 'destructive' : 'default'}
                            onClick={handleToggleStatus}
                        >
                            {employeeIsActive ? 'Desativar' : 'Ativar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
