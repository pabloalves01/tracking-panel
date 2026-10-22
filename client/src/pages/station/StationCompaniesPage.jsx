import { useEffect, useState } from "react"
import { Building2 } from "lucide-react"

import { LayoutToolbar } from "@/components/common/LayoutToolbar"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { formatCpfCnpj } from "@/helpers/formatters"
import { acceptStationCompany, disconnectStationCompany, getStationCompanies, rejectStationCompany } from "@/lib/station/companies"
import { notifyError, notifySuccess } from "@/lib/notify"

const statusLabel = {
    request_send: "Solicitação enviada",
    active: "Ativo",
    rejected: "Recusado",
    disconnected: "Conexão encerrada",
}

export default function StationCompaniesPage() {
    const [links, setLinks] = useState([])
    const [loading, setLoading] = useState(true)

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

    async function handleStatus(link, action) {
        setLoading(true)

        try {
            const response = action === "accept"
                ? await acceptStationCompany(link.company.uuid)
                : action === "disconnect"
                    ? await disconnectStationCompany(link.company.uuid)
                    : await rejectStationCompany(link.company.uuid)
            notifySuccess(response)
            loadLinks()
        } catch (error) {
            notifyError(error)
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
                                            {statusLabel[link.status] ?? link.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex gap-2">
                                            {link.status === "request_send" ? (
                                                <Button type="button" size="sm" onClick={() => handleStatus(link, "accept")}>
                                                    Aceitar
                                                </Button>
                                            ) : null}
                                            {link.status === "request_send" ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleStatus(link, "reject")}
                                                >
                                                    Recusar
                                                </Button>
                                            ) : null}
                                            {link.status === "active" ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleStatus(link, "disconnect")}
                                                >
                                                    Desfazer
                                                </Button>
                                            ) : null}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </LoadingWrapper>
        </>
    )
}
