import { useCallback, useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { BanIcon, BuildingIcon, CircleCheckIcon, PencilIcon, PlusIcon, SearchIcon } from 'lucide-react'

import { ActionButton } from '@/components/common/button/ActionButton'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
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
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatCpfCnpj } from '@/helpers/formatters'
import {
    activateOrganization,
    deactivateOrganization,
    getOrganizations,
} from '@/lib/system/organizations'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/sistema/organizacoes'

/**
 * Todas as organizações do sistema — empresas e postos na mesma lista,
 * distinguidas pelo selo de tipo.
 */
export default function OrganizationsPage() {
    const navigate = useNavigate()
    const [searchParams, setSearchParams] = useSearchParams()
    const [organizations, setOrganizations] = useState([])
    const [meta, setMeta] = useState(null)
    const [loading, setLoading] = useState(true)
    const [toToggle, setToToggle] = useState(null)

    const search = searchParams.get('busca') ?? ''
    const page = Number(searchParams.get('pagina') ?? 1)
    const [draft, setDraft] = useState(search)

    useEffect(() => setDraft(search), [search])

    const load = useCallback(() => {
        setLoading(true)
        getOrganizations({ search: search || undefined, page })
            .then((response) => {
                setOrganizations(response.data ?? [])
                setMeta(response.meta ?? null)
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [search, page])

    useEffect(() => {
        load()
    }, [load])

    async function handleToggle() {
        if (!toToggle) return
        const isActive = toToggle.status === 'active'

        try {
            notifySuccess(
                isActive
                    ? await deactivateOrganization(toToggle.uuid)
                    : await activateOrganization(toToggle.uuid)
            )
            load()
        } catch (error) {
            notifyError(error)
        } finally {
            setToToggle(null)
        }
    }

    const toggleIsActive = toToggle?.status === 'active'

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Sistema', to: '/app/sistema' },
                    { text: 'Organizações' },
                ]}
                slot={
                    <form
                        onSubmit={(event) => {
                            event.preventDefault()
                            const next = new URLSearchParams(searchParams)
                            if (draft.trim()) next.set('busca', draft.trim())
                            else next.delete('busca')
                            next.delete('pagina')
                            setSearchParams(next)
                        }}
                        className="relative"
                    >
                        <SearchIcon className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                        <Input
                            value={draft}
                            onChange={(event) => setDraft(event.target.value)}
                            placeholder="Nome ou CNPJ"
                            aria-label="Procurar organizações"
                            className="w-56 pl-8"
                        />
                    </form>
                }
                buttons={[{ title: 'Nova empresa', icon: PlusIcon, to: `${listPath}/nova` }]}
            />

            {loading ? (
                <div className="grid gap-2" aria-busy="true">
                    {Array.from({ length: 6 }).map((_, index) => (
                        <Skeleton key={index} className="h-11 w-full" />
                    ))}
                </div>
            ) : organizations.length === 0 ? (
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <BuildingIcon />
                        </EmptyMedia>
                        <EmptyTitle>
                            {search ? `Nenhum resultado para "${search}"` : 'Nenhuma organização'}
                        </EmptyTitle>
                        <EmptyDescription>
                            {search
                                ? 'Procure por outro nome ou CNPJ.'
                                : 'Cadastre a primeira empresa para começar.'}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nome</TableHead>
                                <TableHead>CNPJ</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Situação</TableHead>
                                <TableHead className="w-0">Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {organizations.map((organization) => {
                                const isActive = organization.status === 'active'

                                return (
                                    <TableRow key={organization.uuid}>
                                        <TableCell>
                                            <span className="font-medium">
                                                {organization.display_name}
                                            </span>
                                            <span className="text-muted-foreground block text-xs">
                                                {organization.legal_name}
                                            </span>
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {formatCpfCnpj(organization.document)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{organization.type_label}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={isActive ? 'outline' : 'secondary'}>
                                                {isActive ? 'Ativa' : 'Inativa'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1">
                                                <ActionButton
                                                    icon={PencilIcon}
                                                    tooltip="Editar"
                                                    onClick={() =>
                                                        navigate(`${listPath}/${organization.uuid}/editar`)
                                                    }
                                                />
                                                <ActionButton
                                                    icon={isActive ? BanIcon : CircleCheckIcon}
                                                    tooltip={isActive ? 'Desativar' : 'Ativar'}
                                                    variant="outline"
                                                    onClick={() => setToToggle(organization)}
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                )
                            })}
                        </TableBody>
                    </Table>

                    {meta && meta.last_page > 1 ? (
                        <div className="mt-4 flex items-center justify-between">
                            <p className="text-muted-foreground text-xs">
                                {meta.from}–{meta.to} de {meta.total}
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={page <= 1}
                                    onClick={() => {
                                        const next = new URLSearchParams(searchParams)
                                        next.set('pagina', String(page - 1))
                                        setSearchParams(next)
                                    }}
                                >
                                    Anterior
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={page >= meta.last_page}
                                    onClick={() => {
                                        const next = new URLSearchParams(searchParams)
                                        next.set('pagina', String(page + 1))
                                        setSearchParams(next)
                                    }}
                                >
                                    Próxima
                                </Button>
                            </div>
                        </div>
                    ) : null}
                </>
            )}

            <AlertDialog open={!!toToggle} onOpenChange={(open) => !open && setToToggle(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {toggleIsActive ? 'Desativar' : 'Ativar'} organização
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {toggleIsActive
                                ? `Ninguém consegue entrar em ${toToggle?.display_name} enquanto estiver inativa.`
                                : `${toToggle?.display_name} volta a aceitar acessos.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            variant={toggleIsActive ? 'destructive' : 'default'}
                            onClick={handleToggle}
                        >
                            {toggleIsActive ? 'Desativar' : 'Ativar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
