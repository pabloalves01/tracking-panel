import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import {
    BanIcon,
    CircleCheckIcon,
    PencilIcon,
    PlusIcon,
    SearchIcon,
    Trash2Icon,
    UsersIcon,
} from 'lucide-react'

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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { formatCpfCnpj, formatPhone } from '@/helpers/formatters'
import {
    activateEntityRole,
    deactivateEntityRole,
    getEntities,
    removeEntityRole,
} from '@/lib/registry/entities'
import { ENTITY_ROLES, inflect, rolePath, roleFromSlug } from '@/lib/registry/roles'
import { notifyError, notifySuccess } from '@/lib/notify'

const kindLabel = {
    individual: 'Pessoa física',
    organization: 'Pessoa jurídica',
}

/**
 * A lista de um papel. Cada linha é o registro do papel — com o `uuid` e a
 * situação dele — e a pessoa em `entity`.
 *
 * A busca e a paginação são novas: a versão anterior carregava a tabela
 * inteira a cada abertura e não tinha como procurar ninguém.
 */
export default function EntityListPage() {
    const { role: slug } = useParams()
    const navigate = useNavigate()
    const [searchParams, setSearchParams] = useSearchParams()

    const config = roleFromSlug(slug) ?? ENTITY_ROLES.customer
    const basePath = rolePath(config)

    const [records, setRecords] = useState([])
    const [meta, setMeta] = useState(null)
    const [loading, setLoading] = useState(true)
    const [toToggle, setToToggle] = useState(null)
    const [toRemove, setToRemove] = useState(null)

    const search = searchParams.get('busca') ?? ''
    const page = Number(searchParams.get('pagina') ?? 1)
    const [searchDraft, setSearchDraft] = useState(search)

    useEffect(() => setSearchDraft(search), [search])

    const load = useCallback(() => {
        setLoading(true)
        getEntities(config.role, { search: search || undefined, page })
            .then((response) => {
                setRecords(response.data ?? [])
                setMeta(response.meta ?? null)
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [config.role, search, page])

    useEffect(() => {
        load()
    }, [load])

    function submitSearch(event) {
        event.preventDefault()
        const next = new URLSearchParams(searchParams)

        if (searchDraft.trim()) next.set('busca', searchDraft.trim())
        else next.delete('busca')

        next.delete('pagina')
        setSearchParams(next)
    }

    function goToPage(nextPage) {
        const next = new URLSearchParams(searchParams)
        next.set('pagina', String(nextPage))
        setSearchParams(next)
    }

    async function handleToggle() {
        if (!toToggle) return
        const active = toToggle.status !== 'inactive'

        try {
            const response = active
                ? await deactivateEntityRole(config.role, toToggle.uuid)
                : await activateEntityRole(config.role, toToggle.uuid)
            notifySuccess(response)
            load()
        } catch (error) {
            notifyError(error)
        } finally {
            setToToggle(null)
        }
    }

    async function handleRemove() {
        if (!toRemove) return

        try {
            notifySuccess(await removeEntityRole(config.role, toRemove.uuid))
            load()
        } catch (error) {
            notifyError(error)
        } finally {
            setToRemove(null)
        }
    }

    const toggleIsActive = toToggle ? toToggle.status !== 'inactive' : true
    const removeIsLastRole = toRemove ? (toRemove.entity.roles?.length ?? 0) <= 1 : false

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Entidades', to: '/app/cadastros/entidades' },
                    { text: config.plural },
                ]}
                slot={
                    <form onSubmit={submitSearch} className="relative">
                        <SearchIcon className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                        <Input
                            value={searchDraft}
                            onChange={(event) => setSearchDraft(event.target.value)}
                            placeholder="Nome ou documento"
                            aria-label={`Procurar ${config.plural.toLowerCase()}`}
                            className="w-56 pl-8"
                        />
                    </form>
                }
                buttons={[
                    {
                        title: `${config.feminine ? 'Nova' : 'Novo'} ${config.label.toLowerCase()}`,
                        icon: PlusIcon,
                        to: `${basePath}/novo`,
                    },
                ]}
            />

            {loading ? (
                <TableSkeleton />
            ) : records.length === 0 ? (
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <UsersIcon />
                        </EmptyMedia>
                        <EmptyTitle>
                            {search
                                ? `Nenhum resultado para "${search}"`
                                : `${config.feminine ? 'Nenhuma' : 'Nenhum'} ${config.label.toLowerCase()}`}
                        </EmptyTitle>
                        <EmptyDescription>
                            {search
                                ? 'Procure por outro nome ou documento.'
                                : `Cadastre ${config.indefinite === 'uma' ? 'a primeira' : 'o primeiro'} ${config.label.toLowerCase()} para começar.`}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nome</TableHead>
                                <TableHead>Documento</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Contato</TableHead>
                                <TableHead>Outros papéis</TableHead>
                                <TableHead>Situação</TableHead>
                                <TableHead className="w-0">Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {records.map((record) => {
                                const { entity } = record
                                const isActive = record.status !== 'inactive'
                                const others = (entity.roles ?? []).filter(
                                    (item) => item.role !== config.role
                                )

                                return (
                                    <TableRow key={record.uuid}>
                                        <TableCell>
                                            <span className="font-medium">{entity.name}</span>
                                            {entity.trade_name ? (
                                                <span className="text-muted-foreground block text-xs">
                                                    {entity.trade_name}
                                                </span>
                                            ) : null}
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {formatCpfCnpj(entity.document)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {kindLabel[entity.kind] ?? entity.kind}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {entity.email ? (
                                                <span className="block text-sm">{entity.email}</span>
                                            ) : null}
                                            {entity.phone ? (
                                                <span className="text-muted-foreground block text-xs tabular-nums">
                                                    {formatPhone(entity.phone)}
                                                </span>
                                            ) : null}
                                        </TableCell>
                                        <TableCell>
                                            {others.length === 0 ? (
                                                <span className="text-muted-foreground text-xs">—</span>
                                            ) : (
                                                <div className="flex flex-wrap gap-1">
                                                    {others.map((item) => (
                                                        <Badge
                                                            key={item.role}
                                                            variant="secondary"
                                                            render={
                                                                <Link
                                                                    to={`/app/cadastros/entidades/${item.slug}/${item.uuid}/editar`}
                                                                />
                                                            }
                                                        >
                                                            {item.label}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={isActive ? 'outline' : 'secondary'}>
                                                {isActive ? 'Ativo' : 'Inativo'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1">
                                                <ActionButton
                                                    icon={PencilIcon}
                                                    tooltip="Editar"
                                                    onClick={() =>
                                                        navigate(`${basePath}/${record.uuid}/editar`)
                                                    }
                                                />
                                                <ActionButton
                                                    icon={isActive ? BanIcon : CircleCheckIcon}
                                                    tooltip={isActive ? 'Desativar' : 'Ativar'}
                                                    variant="outline"
                                                    onClick={() => setToToggle(record)}
                                                />
                                                <ActionButton
                                                    icon={Trash2Icon}
                                                    tooltip="Excluir"
                                                    variant="destructive"
                                                    onClick={() => setToRemove(record)}
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
                                    onClick={() => goToPage(page - 1)}
                                >
                                    Anterior
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={page >= meta.last_page}
                                    onClick={() => goToPage(page + 1)}
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
                            {toggleIsActive ? 'Desativar' : 'Ativar'} {config.label.toLowerCase()}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {toggleIsActive
                                ? `${toToggle?.entity.name} continua na lista, ${inflect(config, 'marcad')} como ${inflect(config, 'inativ')}, e pode ser ${inflect(config, 'ativad')} de novo a qualquer momento. Os outros cadastros dessa pessoa não mudam.`
                                : `${toToggle?.entity.name} volta a ficar ${inflect(config, 'ativ')} em ${config.plural.toLowerCase()}.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={handleToggle}>
                            {toggleIsActive ? 'Desativar' : 'Ativar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={!!toRemove} onOpenChange={(open) => !open && setToRemove(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Excluir {config.label.toLowerCase()}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {removeIsLastRole
                                ? `${config.plural} é o único cadastro de ${toRemove?.entity.name}. Excluindo, a pessoa sai da lista de entidades.`
                                : `${toRemove?.entity.name} deixa de ser ${config.indefinite} ${config.label.toLowerCase()}, mas continua nos outros cadastros.`}{' '}
                            Se for cadastrad{config.article} de novo, volta com o mesmo registro.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={handleRemove}>
                            Excluir
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}

/**
 * Esqueleto das linhas. O carregamento não derruba mais a tela inteira:
 * cabeçalho, breadcrumb e botão ficam no lugar.
 */
function TableSkeleton() {
    return (
        <div className="grid gap-2" aria-busy="true" aria-live="polite">
            <span className="sr-only">Carregando</span>
            {Array.from({ length: 6 }).map((_, index) => (
                <Skeleton key={index} className="h-11 w-full" />
            ))}
        </div>
    )
}
