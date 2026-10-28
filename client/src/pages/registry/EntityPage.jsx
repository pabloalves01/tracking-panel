import { useCallback, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { ContactIcon, SearchIcon } from 'lucide-react'

import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { Input } from '@/components/ui/input'
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
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
import { getAllEntities } from '@/lib/registry/entities'
import { ROLE_LIST } from '@/lib/registry/roles'
import { notifyError } from '@/lib/notify'

const kindLabel = {
    individual: 'Pessoa física',
    organization: 'Pessoa jurídica',
}

const roleFilterOptions = [
    { value: 'todos', label: 'Todos os papéis' },
    ...ROLE_LIST.map((item) => ({ value: item.role, label: item.plural })),
]

/**
 * Todas as entidades da empresa, de qualquer papel.
 *
 * É onde se procura alguém sem saber em qual cadastro ele está — a pergunta
 * que o desenho anterior, com uma tabela por papel, não conseguia responder.
 */
export default function EntityPage() {
    const [searchParams, setSearchParams] = useSearchParams()
    const [entities, setEntities] = useState([])
    const [meta, setMeta] = useState(null)
    const [loading, setLoading] = useState(true)

    const search = searchParams.get('busca') ?? ''
    const role = searchParams.get('papel') ?? 'todos'
    const page = Number(searchParams.get('pagina') ?? 1)
    const [searchDraft, setSearchDraft] = useState(search)

    useEffect(() => setSearchDraft(search), [search])

    const load = useCallback(() => {
        setLoading(true)
        getAllEntities({
            search: search || undefined,
            role: role === 'todos' ? undefined : role,
            page,
        })
            .then((response) => {
                setEntities(response.data ?? [])
                setMeta(response.meta ?? null)
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [search, role, page])

    useEffect(() => {
        load()
    }, [load])

    function update(changes) {
        const next = new URLSearchParams(searchParams)

        Object.entries(changes).forEach(([key, value]) => {
            if (value) next.set(key, value)
            else next.delete(key)
        })

        next.delete('pagina')
        setSearchParams(next)
    }

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Entidades' },
                ]}
                slot={
                    <div className="flex items-center gap-2">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault()
                                update({ busca: searchDraft.trim() })
                            }}
                            className="relative"
                        >
                            <SearchIcon className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                            <Input
                                value={searchDraft}
                                onChange={(event) => setSearchDraft(event.target.value)}
                                placeholder="Nome ou documento"
                                aria-label="Procurar entidades"
                                className="w-56 pl-8"
                            />
                        </form>
                        <Select
                            items={roleFilterOptions}
                            value={role}
                            onValueChange={(value) =>
                                update({ papel: value === 'todos' ? null : value })
                            }
                        >
                            <SelectTrigger className="w-44" aria-label="Filtrar por papel">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {roleFilterOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                }
            />

            {loading ? (
                <div className="grid gap-2" aria-busy="true">
                    {Array.from({ length: 6 }).map((_, index) => (
                        <Skeleton key={index} className="h-11 w-full" />
                    ))}
                </div>
            ) : entities.length === 0 ? (
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <ContactIcon />
                        </EmptyMedia>
                        <EmptyTitle>
                            {search ? `Nenhum resultado para "${search}"` : 'Nenhuma entidade'}
                        </EmptyTitle>
                        <EmptyDescription>
                            {search
                                ? 'Procure por outro nome ou documento.'
                                : 'Cadastre alguém por um dos papéis no menu ao lado.'}
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
                                <TableHead>Papéis</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {entities.map((entity) => (
                                <TableRow key={entity.uuid}>
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
                                        <div className="flex flex-wrap gap-1">
                                            {(entity.roles ?? []).map((item) => (
                                                <Badge
                                                    key={item.role}
                                                    variant={
                                                        item.status === 'inactive'
                                                            ? 'outline'
                                                            : 'secondary'
                                                    }
                                                    render={
                                                        <Link
                                                            to={`/app/cadastros/entidades/${item.slug}/${entity.uuid}/editar`}
                                                        />
                                                    }
                                                >
                                                    {item.label}
                                                </Badge>
                                            ))}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
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
        </>
    )
}
