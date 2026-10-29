import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import {
    ArrowRightLeftIcon,
    BanIcon,
    CircleCheckIcon,
    PencilIcon,
    PlugZapIcon,
    PlusIcon,
    RadioTowerIcon,
    SearchIcon,
    Trash2Icon,
    UnplugIcon,
} from 'lucide-react'

import { ActionButton } from '@/components/common/button/ActionButton'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { TabButton } from '@/components/common/TabButton'
import { InstallDeviceDialog } from '@/components/fleet/InstallDeviceDialog'
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
import { formatPhone } from '@/helpers/formatters'
import {
    activateDevice,
    deactivateDevice,
    getDevices,
    removeDevice,
    uninstallDevice,
} from '@/lib/fleet/devices'
import { INSTALLATION_ROLE_LABELS, vehiclePath } from '@/lib/fleet/options'
import { useRemoteList } from '@/hooks/use-remote-list'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/frota/rastreadores'

const filters = [
    { key: '', label: 'Todos' },
    { key: '1', label: 'Instalados' },
    { key: '0', label: 'Disponíveis' },
]

/**
 * Os rastreadores da empresa, instalados ou disponíveis. Instalar,
 * transferir e retirar são feitos daqui; no veículo, o rastreador é só um
 * campo de busca.
 */
export default function DevicesPage() {
    const navigate = useNavigate()
    const [searchParams, setSearchParams] = useSearchParams()

    const [toInstall, setToInstall] = useState(null)
    const [toUninstall, setToUninstall] = useState(null)
    const [toToggle, setToToggle] = useState(null)
    const [toRemove, setToRemove] = useState(null)

    const search = searchParams.get('busca') ?? ''
    const installed = searchParams.get('instalados') ?? ''
    const page = Number(searchParams.get('pagina') ?? 1)
    const { records, meta, loading, reload } = useRemoteList(getDevices, { search: search || undefined, installed: installed || undefined, page })

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

    async function run(action, close) {
        try {
            notifySuccess(await action())
            reload()
        } catch (error) {
            notifyError(error)
        } finally {
            close(null)
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
                    { text: 'Rastreadores' },
                ]}
                slot={
                    <form onSubmit={submitSearch} className="relative">
                        <SearchIcon className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                        <Input
                            key={search}
                            name="busca"
                            defaultValue={search}
                            placeholder="IMEI, chip ou placa"
                            aria-label="Procurar rastreadores"
                            className="w-56 pl-8"
                        />
                    </form>
                }
                buttons={[{ title: 'Novo rastreador', icon: PlusIcon, to: `${listPath}/novo` }]}
            />

            <div role="tablist" className="mb-4 flex gap-1 border-b">
                {filters.map((item) => (
                    <TabButton key={item.key} active={installed === item.key} onClick={() => setParam('instalados', item.key)}>
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
                            <RadioTowerIcon />
                        </EmptyMedia>
                        <EmptyTitle>{search ? `Nenhum resultado para "${search}"` : 'Nenhum rastreador'}</EmptyTitle>
                        <EmptyDescription>
                            {search
                                ? 'Procure por outro IMEI, número de chip ou placa.'
                                : 'Cadastre o rastreador antes de instalar: ele existe mesmo sem veículo.'}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Modelo</TableHead>
                                <TableHead>IMEI</TableHead>
                                <TableHead>Chip</TableHead>
                                <TableHead>Veículo</TableHead>
                                <TableHead>Situação</TableHead>
                                <TableHead className="w-0">Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {records.map((device) => {
                                const isActive = device.status !== 'inactive'

                                return (
                                    <TableRow key={device.uuid}>
                                        <TableCell>{device.model?.label}</TableCell>
                                        <TableCell>
                                            <span className="font-mono text-sm tabular-nums">{device.imei}</span>
                                            {device.tracking_id ? (
                                                <span className="text-muted-foreground block text-xs">ID {device.tracking_id}</span>
                                            ) : null}
                                        </TableCell>
                                        <TableCell>
                                            {device.sim_number ? (
                                                <span className="block text-sm tabular-nums">{formatPhone(device.sim_number)}</span>
                                            ) : (
                                                <span className="text-muted-foreground text-xs">—</span>
                                            )}
                                            {device.sim_iccid ? (
                                                <span className="text-muted-foreground block font-mono text-xs">{device.sim_iccid}</span>
                                            ) : null}
                                        </TableCell>
                                        <TableCell>
                                            {device.vehicle ? (
                                                <span className="flex items-center gap-2">
                                                    <Link
                                                        className="font-mono font-medium tracking-wider underline-offset-4 hover:underline"
                                                        to={vehiclePath(device.vehicle.kind, device.vehicle.uuid, 'equipamentos')}
                                                    >
                                                        {device.vehicle.plate}
                                                    </Link>
                                                    <Badge variant="secondary">{INSTALLATION_ROLE_LABELS[device.vehicle.role]}</Badge>
                                                </span>
                                            ) : (
                                                <Badge variant="outline">Disponível</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={isActive ? 'outline' : 'secondary'}>{isActive ? 'Ativo' : 'Inativo'}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1">
                                                <ActionButton
                                                    icon={PencilIcon}
                                                    tooltip="Editar"
                                                    onClick={() => navigate(`${listPath}/${device.uuid}/editar`)}
                                                />
                                                <ActionButton
                                                    icon={device.vehicle ? ArrowRightLeftIcon : PlugZapIcon}
                                                    tooltip={device.vehicle ? 'Transferir para outro veículo' : 'Instalar em veículo'}
                                                    disabled={!isActive}
                                                    onClick={() => setToInstall(device)}
                                                />
                                                {device.vehicle ? (
                                                    <ActionButton
                                                        icon={UnplugIcon}
                                                        tooltip="Retirar do veículo"
                                                        onClick={() => setToUninstall(device)}
                                                    />
                                                ) : null}
                                                <ActionButton
                                                    icon={isActive ? BanIcon : CircleCheckIcon}
                                                    tooltip={isActive ? 'Desativar' : 'Ativar'}
                                                    disabled={isActive && Boolean(device.vehicle)}
                                                    onClick={() => setToToggle(device)}
                                                />
                                                <ActionButton
                                                    icon={Trash2Icon}
                                                    tooltip="Excluir"
                                                    variant="destructive"
                                                    onClick={() => setToRemove(device)}
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

            {toInstall ? (
                <InstallDeviceDialog
                    device={toInstall}
                    onClose={() => setToInstall(null)}
                    onDone={() => {
                        setToInstall(null)
                        reload()
                    }}
                />
            ) : null}

            <AlertDialog open={!!toUninstall} onOpenChange={(open) => !open && setToUninstall(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Retirar de {toUninstall?.vehicle?.plate}</AlertDialogTitle>
                        <AlertDialogDescription>
                            O rastreador {toUninstall?.imei} fica disponível para outro veículo. A instalação continua no histórico.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={() => run(() => uninstallDevice(toUninstall.uuid), setToUninstall)}>
                            Retirar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={!!toToggle} onOpenChange={(open) => !open && setToToggle(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {toggleIsActive ? 'Desativar' : 'Ativar'} rastreador {toToggle?.imei}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {toggleIsActive
                                ? 'Inativo, ele não pode ser instalado até ser ativado de novo.'
                                : 'O rastreador volta a poder ser instalado.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() =>
                                run(
                                    () => (toggleIsActive ? deactivateDevice(toToggle.uuid) : activateDevice(toToggle.uuid)),
                                    setToToggle
                                )
                            }
                        >
                            {toggleIsActive ? 'Desativar' : 'Ativar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={!!toRemove} onOpenChange={(open) => !open && setToRemove(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Excluir rastreador {toRemove?.imei}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {toRemove?.vehicle ? `Ele é retirado de ${toRemove.vehicle.plate} antes. ` : ''}
                            O histórico de instalações continua. Se o IMEI for cadastrado de novo, volta com o mesmo registro.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={() => run(() => removeDevice(toRemove.uuid), setToRemove)}>
                            Excluir
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
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
