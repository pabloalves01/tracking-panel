import { useEffect, useState } from "react"
import { Building2 } from "lucide-react"

import { LayoutToolbar } from "@/components/common/LayoutToolbar"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
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
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { formatCpfCnpj } from "@/helpers/formatters"
import { linkStatusLabel } from "@/helpers/labels/linkStatusLabel"
import { useSession } from "@/lib/auth/session"
import { acceptStationCompany, disconnectStationCompany, getStationCompanies, rejectStationCompany } from "@/lib/station/companies"
import { notifyError, notifySuccess } from "@/lib/notify"

function companyLabel(link) {
    return link?.company?.trade_name || link?.company?.legal_name || "esta empresa"
}

export default function StationCompaniesPage() {
    const { user } = useSession()
    const canManage = user?.current_role === "owner" || user?.current_role === "admin"
    const [links, setLinks] = useState([])
    const [loading, setLoading] = useState(true)
    const [linkToUndo, setLinkToUndo] = useState(null)
    const [linkToReject, setLinkToReject] = useState(null)

    useEffect(() => {
        getStationCompanies()
            .then(setLinks)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [])

    function loadLinks() {
        setLoading(true)
        getStationCompanies()
            .then(setLinks)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    async function handleAccept(link) {
        setLoading(true)

        try {
            notifySuccess(await acceptStationCompany(link.company.uuid))
            loadLinks()
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    async function handleReject() {
        if (!linkToReject) return
        setLoading(true)

        try {
            notifySuccess(await rejectStationCompany(linkToReject.company.uuid))
            loadLinks()
        } catch (error) {
            notifyError(error)
        } finally {
            setLinkToReject(null)
            setLoading(false)
        }
    }

    async function handleDisconnect() {
        if (!linkToUndo) {
            return
        }

        setLoading(true)

        try {
            const response = await disconnectStationCompany(linkToUndo.company.uuid)
            notifySuccess(response)
            loadLinks()
        } catch (error) {
            notifyError(error)
        } finally {
            setLinkToUndo(null)
            setLoading(false)
        }
    }

    return (
        <>
            <LayoutToolbar breadcrumbs={[{ text: "Início", to: "/posto" }, { text: "Empresas" }]} />

            <LoadingWrapper loading={loading}>
                {!loading && links.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Building2 />
                            </EmptyMedia>
                            <EmptyTitle>Nenhuma empresa</EmptyTitle>
                            <EmptyDescription>
                                Os pedidos de vínculo aparecem aqui quando uma empresa informa o CNPJ deste posto.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Empresa</TableHead>
                                <TableHead>CNPJ</TableHead>
                                <TableHead>Situação</TableHead>
                                <TableHead>Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {links.map((link) => (
                                <TableRow key={link.company.uuid}>
                                    <TableCell>
                                        {link.company.trade_name || link.company.legal_name}
                                    </TableCell>
                                    <TableCell>{formatCpfCnpj(link.company.cnpj)}</TableCell>
                                    <TableCell>
                                        <Badge variant={link.status === "active" ? "secondary" : "outline"}>
                                            {linkStatusLabel[link.status] ?? link.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {canManage ? (
                                            <div className="flex gap-2">
                                                {link.status === "request_sent" ? (
                                                    <Button type="button" size="sm" onClick={() => handleAccept(link)}>
                                                        Aceitar
                                                    </Button>
                                                ) : null}
                                                {link.status === "request_sent" ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => setLinkToReject(link)}
                                                    >
                                                        Recusar
                                                    </Button>
                                                ) : null}
                                                {link.status === "active" ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => setLinkToUndo(link)}
                                                    >
                                                        Desfazer
                                                    </Button>
                                                ) : null}
                                            </div>
                                        ) : null}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </LoadingWrapper>

            <AlertDialog open={!!linkToUndo} onOpenChange={(open) => !open && setLinkToUndo(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Desfazer vínculo</AlertDialogTitle>
                        <AlertDialogDescription>
                            {`Tem certeza que deseja desfazer o vínculo com ${companyLabel(linkToUndo)}?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={handleDisconnect}>
                            Desfazer
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={!!linkToReject} onOpenChange={(open) => !open && setLinkToReject(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Recusar pedido</AlertDialogTitle>
                        <AlertDialogDescription>
                            {`Tem certeza que deseja recusar o pedido de ${companyLabel(linkToReject)}?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={handleReject}>
                            Recusar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
