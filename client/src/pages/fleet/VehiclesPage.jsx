import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { BanIcon, CircleCheckIcon, PencilIcon, PlusIcon, SearchIcon, Trash2Icon, TruckIcon } from 'lucide-react'

import { ActionButton } from '@/components/common/button/ActionButton'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { TabButton } from '@/components/common/TabButton'
import { ExpiryBadge } from '@/components/fleet/ExpiryBadge'
import { ColorDot } from '@/components/fleet/SelectVehicleColor'
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
import { activateVehicle, deactivateVehicle, getVehicles, removeVehicle } from '@/lib/fleet/vehicles'
import { useRemoteList } from '@/hooks/use-remote-list'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/frota/veiculos'

const kinds = [
    { key: '', label: 'Todos' },
    { key: 'motor_vehicle', label: 'Automotores' },
    { key: 'trailer', label: 'Implementos' },
]

/**
 * A frota: automotores e implementos numa lista só, com o filtro por grupo.
 * Cada linha mostra a composição e o rastreador principal, e a validade do
 * CRLV em destaque quando vence em breve.
 */
export default function VehiclesPage() {
    const navigate = useNavigate()
    const [searchParams, setSearchParams] = useSearchParams()

    const [toToggle, setToToggle] = useState(null)
    const [toRemove, setToRemove] = useState(null)

    const search = searchParams.get('busca') ?? ''
    const kind = searchParams.get('grupo') ?? ''
    const page = Number(searchParams.get('pagina') ?? 1)
    const { records, meta, loading, reload } = useRemoteList(getVehicles, { search: search || undefined, kind: kind || undefined, page })

    function setParam(key, value) {
        const next = new URLSearchParams(searchParams)

        if (value) next.set(key, value)
        else next.delete(key)

        if (key !== 'pagina') next.delete('pagina')
        setSearchParams(next)
    }

    function submitSearch(event) {
        event.preventDefault()
        setParam('busca', String(new FormData(event.currentTarget).get('busca') ?? '').trim())
    }

    async function handleToggle() {
        if (!toToggle) return
        const active = toToggle.status !== 'inactive'

        try {
            notifySuccess(active ? await deactivateVehicle(toToggle.uuid) : await activateVehicle(toToggle.uuid))
            reload()
        } catch (error) {
            notifyError(error)
        } finally {
            setToToggle(null)
        }
    }

    async function handleRemove() {
        if (!toRemove) return

        try {
            notifySuccess(await removeVehicle(toRemove.uuid))
            reload()
        } catch (error) {
            notifyError(error)
        } finally {
            setToRemove(null)
        }
    }

    const toggleIsActive = toToggle ? toToggle.status !== 'inactive' : true

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Frota', to: '/app/cadastros/frota' },
                    { text: 'Veículos' },
                ]}
                slot={
                    <form onSubmit={submitSearch} className="relative">
                        <SearchIcon className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                        <Input
                            key={search}
                            name="busca"
                            defaultValue={search}
                            placeholder="Placa, chassi ou proprietário"
                            aria-label="Procurar veículos"
                            className="w-64 pl-8"
                        />
                    </form>
                }
                buttons={[{ title: 'Novo veículo', icon: PlusIcon, to: `${listPath}/novo` }]}
            />

            <div role="tablist" className="mb-4 flex gap-1 border-b">
                {kinds.map((item) => (
                    <TabButton key={item.key} active={kind === item.key} onClick={() => setParam('grupo', item.key)}>
                        {item.label}
                    </TabButton>
                ))}
            </div>

            {loading ? (
                <TableSkeleton />
            ) : records.length === 0 ? (
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <TruckIcon />
                        </EmptyMedia>
                        <EmptyTitle>{search ? `Nenhum resultado para "${search}"` : 'Nenhum veículo'}</EmptyTitle>
                        <EmptyDescription>
                            {search
                                ? 'Procure por outra placa, chassi ou proprietário.'
                                : 'Cadastre o primeiro veículo da frota — cavalo, caminhão ou implemento.'}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Placa</TableHead>
                                <TableHead>Veículo</TableHead>
                                <TableHead>Proprietário · responsável</TableHead>
                                <TableHead>Composição</TableHead>
                                <TableHead>Rastreador</TableHead>
                                <TableHead>CRLV</TableHead>
                                <TableHead>Situação</TableHead>
                                <TableHead className="w-0">Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {records.map((vehicle) => {
                                const isActive = vehicle.status !== 'inactive'
                                const primary = vehicle.devices.find((device) => device.role === 'primary') ?? vehicle.devices[0]

                                return (
                                    <TableRow key={vehicle.uuid}>
                                        <TableCell>
                                            <span className="font-mono font-medium tracking-wider">{vehicle.plate}</span>
                                            <span className="text-muted-foreground block text-xs">{vehicle.plate_state}</span>
                                        </TableCell>
                                        <TableCell>
                                            <span className="flex items-center gap-1.5">
                                                <ColorDot value={vehicle.color} />
                                                {vehicle.brand} {vehicle.model}
                                            </span>
                                            <span className="text-muted-foreground block text-xs">
                                                {[vehicle.type.name, vehicle.body_type?.name, `${vehicle.manufacture_year}/${vehicle.model_year}`]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <span className="block text-sm">{vehicle.owner?.name}</span>
                                            {vehicle.responsible && vehicle.responsible.document !== vehicle.owner?.document ? (
                                                <span className="text-muted-foreground block text-xs">{vehicle.responsible.name}</span>
                                            ) : null}
                                        </TableCell>
                                        <TableCell>
                                            <Composition vehicle={vehicle} />
                                        </TableCell>
                                        <TableCell>
                                            {primary ? (
                                                <>
                                                    <span className="block font-mono text-xs tabular-nums">{primary.imei}</span>
                                                    <span className="text-muted-foreground block text-xs">
                                                        {primary.model}
                                                        {vehicle.devices.length > 1 ? ` · +${vehicle.devices.length - 1}` : ''}
                                                    </span>
                                                </>
                                            ) : (
                                                <span className="text-muted-foreground text-xs">Sem rastreador</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <ExpiryBadge date={vehicle.registration_expires_on} />
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={isActive ? 'outline' : 'secondary'}>{isActive ? 'Ativo' : 'Inativo'}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1">
                                                <ActionButton
                                                    icon={PencilIcon}
                                                    tooltip="Editar"
                                                    onClick={() => navigate(`${listPath}/${vehicle.uuid}/editar`)}
                                                />
                                                <ActionButton
                                                    icon={isActive ? BanIcon : CircleCheckIcon}
                                                    tooltip={isActive ? 'Desativar' : 'Ativar'}
                                                    onClick={() => setToToggle(vehicle)}
                                                />
                                                <ActionButton
                                                    icon={Trash2Icon}
                                                    tooltip="Excluir"
                                                    variant="destructive"
                                                    onClick={() => setToRemove(vehicle)}
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
                                <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setParam('pagina', String(page - 1))}>
                                    Anterior
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={page >= meta.last_page}
                                    onClick={() => setParam('pagina', String(page + 1))}
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
                            {toggleIsActive ? 'Desativar' : 'Ativar'} {toToggle?.plate}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {toggleIsActive
                                ? 'O veículo continua na lista, marcado como inativo, e pode ser ativado de novo a qualquer momento. Engates e rastreadores não mudam.'
                                : 'O veículo volta a ficar ativo na frota.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={handleToggle}>{toggleIsActive ? 'Desativar' : 'Ativar'}</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={!!toRemove} onOpenChange={(open) => !open && setToRemove(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Excluir {toRemove?.plate}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {toRemove?.trailers?.length > 0 || toRemove?.traction ? 'Os engates são desfeitos. ' : ''}
                            {toRemove?.devices?.length > 0 ? 'Os rastreadores são retirados e ficam disponíveis. ' : ''}
                            Se a placa for cadastrada de novo, o veículo volta com o mesmo registro.
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

/** Automotor: as placas dos implementos. Implemento: em quem está engatado. */
function Composition({ vehicle }) {
    if (vehicle.kind === 'trailer') {
        return vehicle.traction ? (
            <span className="text-xs">
                Engatado em{' '}
                <Link className="font-mono tracking-wider underline" to={`${listPath}/${vehicle.traction.uuid}/editar`}>
                    {vehicle.traction.plate}
                </Link>
            </span>
        ) : (
            <span className="text-muted-foreground text-xs">Desengatado</span>
        )
    }

    if (vehicle.trailers.length === 0) {
        return <span className="text-muted-foreground text-xs">—</span>
    }

    return (
        <div className="flex flex-wrap gap-1">
            {vehicle.trailers.map((trailer) => (
                <Badge
                    key={trailer.uuid}
                    variant="secondary"
                    className="font-mono tracking-wider"
                    render={<Link to={`${listPath}/${trailer.uuid}/editar`} />}
                >
                    {trailer.plate}
                </Badge>
            ))}
        </div>
    )
}

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
