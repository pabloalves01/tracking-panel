import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router'
import { BanIcon, CircleCheckIcon, CopyIcon, MailIcon, PlusIcon, RefreshCw, Trash2Icon, UserPenIcon, UsersIcon } from 'lucide-react'

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
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { invitationStatusLabel } from '@/helpers/labels/linkStatusLabel'
import { roleLabel } from '@/helpers/labels/roleLabel'
import { memberApi } from '@/lib/access/members'
import { copyText, notifyError, notifySuccess } from '@/lib/notify'

function formatWhen(value) {
    if (!value) return '—'

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) return '—'

    return date.toLocaleString('pt-BR')
}

export default function MembersPage({
    breadcrumbs,
    listPath,
    apiBase,
    roleOptions,
    actorEmail,
    actorRole,
}) {
    const access = useMemo(() => memberApi(apiBase), [apiBase])
    const canWrite = true
    const [params, setParams] = useSearchParams()
    const tab = params.get('aba') === 'convites' ? 'convites' : 'usuarios'
    const [members, setMembers] = useState([])
    const [invitations, setInvitations] = useState([])
    const [loading, setLoading] = useState(true)
    const [memberToSuspend, setMemberToSuspend] = useState(null)
    const [memberToRemove, setMemberToRemove] = useState(null)
    const [memberToReactivate, setMemberToReactivate] = useState(null)
    const [memberToEdit, setMemberToEdit] = useState(null)
    const [nextRole, setNextRole] = useState('')
    const [inviteToRevoke, setInviteToRevoke] = useState(null)
    const [loadError, setLoadError] = useState(false)

    useEffect(() => {
        load()
    }, [access])

    function load() {
        setLoading(true)
        setLoadError(false)
        Promise.all([access.list(), access.invitations()])
            .then(([people, invites]) => {
                setMembers(people ?? [])
                setInvitations(invites ?? [])
            })
            .catch((error) => {
                setLoadError(true)
                notifyError(error)
            })
            .finally(() => setLoading(false))
    }

    function selectTab(next) {
        const query = next === 'convites' ? { aba: 'convites' } : {}
        setParams(query, { replace: true })
    }

    function openRole(member) {
        setMemberToEdit(member)
        setNextRole(member.role)
    }

    async function handleRole() {
        if (!memberToEdit || !nextRole || nextRole === memberToEdit.role) {
            setMemberToEdit(null)
            return
        }

        setLoading(true)

        try {
            notifySuccess(await access.updateRole(memberToEdit.uuid, nextRole))
            load()
        } catch (error) {
            notifyError(error)
            setLoading(false)
        } finally {
            setMemberToEdit(null)
        }
    }

    async function handleSuspend() {
        if (!memberToSuspend) return
        setLoading(true)

        try {
            notifySuccess(await access.suspend(memberToSuspend.uuid))
            load()
        } catch (error) {
            notifyError(error)
        } finally {
            setMemberToSuspend(null)
            setLoading(false)
        }
    }

    async function handleReactivate() {
        if (!memberToReactivate) return
        setLoading(true)

        try {
            notifySuccess(await access.reactivate(memberToReactivate.uuid))
            load()
        } catch (error) {
            notifyError(error)
        } finally {
            setMemberToReactivate(null)
            setLoading(false)
        }
    }

    async function handleRemove() {
        if (!memberToRemove) return
        setLoading(true)

        try {
            notifySuccess(await access.remove(memberToRemove.uuid))
            load()
        } catch (error) {
            notifyError(error)
        } finally {
            setMemberToRemove(null)
            setLoading(false)
        }
    }

    async function handleResend(invitation) {
        setLoading(true)

        try {
            const updated = await access.resend(invitation.uuid)
            const url = updated?.invite_url
            notifySuccess(
                { message: 'Convite reenviado' },
                url
                    ? { action: { label: 'Copiar link', onClick: () => copyText(url) } }
                    : undefined,
            )
            load()
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    async function handleRevoke() {
        if (!inviteToRevoke) return
        setLoading(true)

        try {
            notifySuccess(await access.revoke(inviteToRevoke.uuid))
            load()
        } catch (error) {
            notifyError(error)
        } finally {
            setInviteToRevoke(null)
            setLoading(false)
        }
    }

    const pendingCount = invitations.filter((invitation) => invitation.status === 'pending').length

    return (
        <>
            <LayoutToolbar
                breadcrumbs={breadcrumbs}
                buttons={canWrite ? [{ title: 'Novo usuário', icon: PlusIcon, to: `${listPath}/convidar`, permission: 'write' }] : []}
            />

            <div role="tablist" aria-label="Usuários e convites" className="mb-6 flex gap-6 border-b">
                <TabButton selected={tab === 'usuarios'} onClick={() => selectTab('usuarios')}>
                    Usuários
                </TabButton>
                <TabButton selected={tab === 'convites'} onClick={() => selectTab('convites')}>
                    Convites
                    {pendingCount > 0 ? (
                        <Badge variant="outline" className="ml-2">
                            {pendingCount}
                        </Badge>
                    ) : null}
                </TabButton>
            </div>

            <LoadingWrapper loading={loading}>
                {loadError ? (
                    <p className="text-sm text-muted-foreground">Não foi possível carregar esta lista.</p>
                ) : tab === 'usuarios' ? (
                    <MembersTable
                        loading={loading}
                        members={members}
                        canWrite={canWrite}
                        actorEmail={actorEmail}
                        actorRole={actorRole}
                        onRole={openRole}
                        onSuspend={setMemberToSuspend}
                        onReactivate={setMemberToReactivate}
                        onRemove={setMemberToRemove}
                    />
                ) : (
                    <InvitesTable
                        loading={loading}
                        invitations={invitations}
                        canWrite={canWrite}
                        onResend={handleResend}
                        onCopy={copyText}
                        onRevoke={setInviteToRevoke}
                    />
                )}
            </LoadingWrapper>

            <AlertDialog open={!!memberToEdit} onOpenChange={(open) => !open && setMemberToEdit(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Alterar papel</AlertDialogTitle>
                        <AlertDialogDescription>
                            {`Escolha o papel de ${memberToEdit?.name || memberToEdit?.email}.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <Select items={roleOptions} value={nextRole} onValueChange={setNextRole}>
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                {roleOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={handleRole}>Salvar</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <Confirm
                open={!!memberToSuspend}
                title="Suspender usuário"
                description={`Tem certeza que deseja suspender ${memberToSuspend?.name || memberToSuspend?.email}? O acesso a este contexto fica bloqueado.`}
                confirm="Suspender"
                onOpenChange={(open) => !open && setMemberToSuspend(null)}
                onConfirm={handleSuspend}
            />
            <Confirm
                open={!!memberToReactivate}
                title="Reativar usuário"
                description={`Tem certeza que deseja reativar ${memberToReactivate?.name || memberToReactivate?.email}?`}
                confirm="Reativar"
                destructive={false}
                onOpenChange={(open) => !open && setMemberToReactivate(null)}
                onConfirm={handleReactivate}
            />
            <Confirm
                open={!!memberToRemove}
                title="Remover usuário"
                description={`Tem certeza que deseja remover ${memberToRemove?.name || memberToRemove?.email}? O vínculo deixa de aparecer nesta lista.`}
                confirm="Remover"
                onOpenChange={(open) => !open && setMemberToRemove(null)}
                onConfirm={handleRemove}
            />
            <Confirm
                open={!!inviteToRevoke}
                title="Cancelar convite"
                description={`Tem certeza que deseja cancelar o convite de ${inviteToRevoke?.email}? O link deixa de valer.`}
                confirm="Cancelar convite"
                cancel="Voltar"
                onOpenChange={(open) => !open && setInviteToRevoke(null)}
                onConfirm={handleRevoke}
            />
        </>
    )
}

function TabButton({ selected, onClick, children }) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={selected}
            className={`-mb-px inline-flex items-center border-b-2 pb-2 text-sm font-medium ${selected ? 'border-foreground text-foreground' : 'border-transparent text-muted-foreground'}`}
            onClick={onClick}
        >
            {children}
        </button>
    )
}

function MembersTable({ loading, members, canWrite, actorEmail, actorRole, onRole, onSuspend, onReactivate, onRemove }) {
    if (!loading && members.length === 0) {
        return (
            <Empty>
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <UsersIcon />
                    </EmptyMedia>
                    <EmptyTitle>Nenhum usuário</EmptyTitle>
                    <EmptyDescription>Convide a primeira pessoa para este contexto.</EmptyDescription>
                </EmptyHeader>
            </Empty>
        )
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Nome</TableHead>
                    <TableHead>E-mail</TableHead>
                    <TableHead>Papel</TableHead>
                    <TableHead>Situação</TableHead>
                    {canWrite ? <TableHead>Ações</TableHead> : null}
                </TableRow>
            </TableHeader>
            <TableBody>
                {members.map((member) => {
                    const isSelf = member.email === actorEmail
                    const locked = isSelf || (member.role === 'owner' && actorRole !== 'owner')
                    const suspended = member.status === 'suspended'

                    return (
                        <TableRow key={member.uuid}>
                            <TableCell>{member.name}</TableCell>
                            <TableCell>{member.email}</TableCell>
                            <TableCell>
                                <Badge variant="outline">{roleLabel[member.role] ?? member.role}</Badge>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline">{suspended ? 'Suspenso' : 'Ativo'}</Badge>
                            </TableCell>
                            {canWrite ? (
                                <TableCell>
                                    {locked ? null : (
                                        <div className="flex items-center gap-2">
                                            {suspended ? null : (
                                                <ActionButton icon={UserPenIcon} tooltip="Alterar papel" onClick={() => onRole(member)} />
                                            )}
                                            {suspended ? (
                                                <ActionButton
                                                    icon={CircleCheckIcon}
                                                    tooltip="Reativar"
                                                    onClick={() => onReactivate(member)}
                                                />
                                            ) : (
                                                <ActionButton
                                                    icon={BanIcon}
                                                    tooltip="Suspender"
                                                    variant="destructive"
                                                    onClick={() => onSuspend(member)}
                                                />
                                            )}
                                            <ActionButton
                                                icon={Trash2Icon}
                                                tooltip="Remover"
                                                variant="destructive"
                                                onClick={() => onRemove(member)}
                                            />
                                        </div>
                                    )}
                                </TableCell>
                            ) : null}
                        </TableRow>
                    )
                })}
            </TableBody>
        </Table>
    )
}

function InvitesTable({ loading, invitations, canWrite, onResend, onCopy, onRevoke }) {
    if (!loading && invitations.length === 0) {
        return (
            <Empty>
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <MailIcon />
                    </EmptyMedia>
                    <EmptyTitle>Nenhum convite</EmptyTitle>
                    <EmptyDescription>Os convites enviados aparecem aqui.</EmptyDescription>
                </EmptyHeader>
            </Empty>
        )
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>E-mail</TableHead>
                    <TableHead>Papel</TableHead>
                    <TableHead>Situação</TableHead>
                    <TableHead>Enviado em</TableHead>
                    <TableHead>Expira em</TableHead>
                    {canWrite ? <TableHead>Ações</TableHead> : null}
                </TableRow>
            </TableHeader>
            <TableBody>
                {invitations.map((invitation) => (
                    <TableRow key={invitation.uuid}>
                        <TableCell>{invitation.email}</TableCell>
                        <TableCell>
                            <Badge variant="outline">{roleLabel[invitation.role] ?? invitation.role}</Badge>
                        </TableCell>
                        <TableCell>
                            <Badge variant="outline">
                                {invitationStatusLabel[invitation.status] ?? invitation.status}
                            </Badge>
                        </TableCell>
                        <TableCell>{formatWhen(invitation.created_at)}</TableCell>
                        <TableCell>{formatWhen(invitation.expires_at)}</TableCell>
                        {canWrite ? (
                            <TableCell>
                                {invitation.status === 'pending' || invitation.status === 'expired' ? (
                                    <div className="flex items-center gap-2">
                                        <ActionButton icon={RefreshCw} tooltip="Reenviar" onClick={() => onResend(invitation)} />
                                        {invitation.invite_url ? (
                                            <ActionButton icon={CopyIcon} tooltip="Copiar link" onClick={() => onCopy(invitation.invite_url)} />
                                        ) : null}
                                        {invitation.status === 'pending' ? (
                                            <ActionButton
                                                icon={Trash2Icon}
                                                tooltip="Cancelar convite"
                                                variant="destructive"
                                                onClick={() => onRevoke(invitation)}
                                            />
                                        ) : null}
                                    </div>
                                ) : null}
                            </TableCell>
                        ) : null}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    )
}

function Confirm({ open, title, description, confirm, cancel = 'Cancelar', destructive = true, onOpenChange, onConfirm }) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>{description}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{cancel}</AlertDialogCancel>
                    <AlertDialogAction variant={destructive ? 'destructive' : 'default'} onClick={onConfirm}>
                        {confirm}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    )
}
