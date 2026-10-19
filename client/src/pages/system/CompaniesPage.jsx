import { useEffect, useState } from "react"
import { useNavigate } from "react-router"
import { BuildingIcon, PencilIcon, PlusIcon, TrashIcon, User, UserIcon } from "lucide-react"

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog"

import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { ActionButton } from "@/components/common/button/ActionButton"
import { LayoutToolbar } from "@/components/common/LayoutToolbar"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { formatCpfCnpj } from "@/helpers/formatters"
import { deleteCompany, getCompanies } from "@/lib/system/companies"
import { notifyError, notifySuccess } from "@/lib/notify"
import { Badge } from "@/components/ui/badge";

export default function CompaniesPage() {
    const navigate = useNavigate()
    const [companies, setCompanies] = useState([])
    const [loading, setLoading] = useState(false)
    // Empresa escolhida para excluir. Abre o diálogo de confirmação quando preenchida.
    const [companyToDelete, setCompanyToDelete] = useState(null)

    useEffect(() => {
        loadCompanies()
    }, [])

    function loadCompanies() {
        setLoading(true)
        getCompanies()
            .then(setCompanies)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    async function handleDelete() {
        if (!companyToDelete) return
        setLoading(true)

        try {
            const response = await deleteCompany(companyToDelete.uuid)
            notifySuccess(response)
            loadCompanies()
        } catch (error) {
            notifyError(error)
        } finally {
            setCompanyToDelete(null)
            setLoading(false)
        }
    }

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/' },
                    { text: 'Sistema', to: '/app/sistema' },
                    { text: 'Empresas' },
                ]}
                buttons={[
                    { title: 'Nova empresa', icon: PlusIcon, to: '/app/sistema/empresas/novo' },
                ]}
            />

            <LoadingWrapper loading={loading}>
                {!loading && companies.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <BuildingIcon />
                            </EmptyMedia>
                            <EmptyTitle>Nenhuma empresa</EmptyTitle>
                            <EmptyDescription>Cadastre a primeira empresa para começar.</EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Razão social</TableHead>
                                <TableHead>Nome fantasia</TableHead>
                                <TableHead>CNPJ</TableHead>
                                <TableHead>Usuários</TableHead>
                                <TableHead>Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {companies.map((company) => (
                                <TableRow key={company.uuid}>
                                    <TableCell>{company.legal_name}</TableCell>
                                    <TableCell>{company.trade_name}</TableCell>
                                    <TableCell>{formatCpfCnpj(company.cnpj)}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            <UserIcon data-icon="inline-start" /> {company.users_count}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <ActionButton
                                                icon={PencilIcon}
                                                tooltip="Editar"
                                                onClick={() => navigate(`/app/sistema/empresas/${company.uuid}/editar`)}
                                            />
                                            <ActionButton
                                                icon={TrashIcon}
                                                tooltip="Excluir"
                                                variant="destructive"
                                                onClick={() => setCompanyToDelete(company)}
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </LoadingWrapper>

            <AlertDialog open={!!companyToDelete} onOpenChange={(open) => !open && setCompanyToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Excluir empresa</AlertDialogTitle>
                        <AlertDialogDescription>
                            Tem certeza que deseja excluir {companyToDelete?.legal_name}? Essa ação não pode ser desfeita.
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
