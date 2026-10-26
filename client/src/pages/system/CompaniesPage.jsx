import { useEffect, useState } from "react"
import { useNavigate } from "react-router"
import { BanIcon, BuildingIcon, CircleCheckIcon, PencilIcon, PlusIcon, UserIcon, UsersIcon } from "lucide-react"

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
import { activateCompany, deactivateCompany, getCompanies } from "@/lib/system/companies"
import { notifyError, notifySuccess } from "@/lib/notify"
import { Badge } from "@/components/ui/badge";

export default function CompaniesPage() {
    const navigate = useNavigate()
    const [companies, setCompanies] = useState([])
    const [loading, setLoading] = useState(false)
    const [companyToToggle, setCompanyToToggle] = useState(null)

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

    const companyIsActive = companyToToggle?.status !== "inactive"

    async function handleToggleStatus() {
        if (!companyToToggle) return
        setLoading(true)

        try {
            const response = companyIsActive
                ? await deactivateCompany(companyToToggle.uuid)
                : await activateCompany(companyToToggle.uuid)
            notifySuccess(response)
            loadCompanies()
        } catch (error) {
            notifyError(error)
        } finally {
            setCompanyToToggle(null)
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
                                <TableHead>Status</TableHead>
                                <TableHead>Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {companies.map((company) => {
                                const isActive = company.status !== "inactive"

                                return (
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
                                            <Badge variant="outline">{isActive ? "Ativo" : "Inativo"}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <ActionButton
                                                    icon={UsersIcon}
                                                    tooltip="Usuários"
                                                    onClick={() => navigate(`/app/sistema/empresas/${company.uuid}/usuarios`)}
                                                />
                                                <ActionButton
                                                    icon={PencilIcon}
                                                    tooltip="Editar"
                                                    onClick={() => navigate(`/app/sistema/empresas/${company.uuid}/editar`)}
                                                />
                                                <ActionButton
                                                    icon={isActive ? BanIcon : CircleCheckIcon}
                                                    tooltip={isActive ? "Desativar" : "Ativar"}
                                                    variant={isActive ? "destructive" : "outline"}
                                                    onClick={() => setCompanyToToggle(company)}
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

            <AlertDialog open={!!companyToToggle} onOpenChange={(open) => !open && setCompanyToToggle(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {companyIsActive ? "Desativar empresa" : "Ativar empresa"}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {companyIsActive
                                ? `Tem certeza que deseja desativar ${companyToToggle?.legal_name}? O cadastro permanece e pode ser ativado de novo.`
                                : `Tem certeza que deseja ativar ${companyToToggle?.legal_name}?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            variant={companyIsActive ? "destructive" : "default"}
                            onClick={handleToggleStatus}
                        >
                            {companyIsActive ? "Desativar" : "Ativar"}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
