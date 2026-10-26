import { useEffect, useMemo, useState } from 'react'
import { MailIcon, PlusIcon, RefreshCw, Trash2Icon, UsersIcon } from 'lucide-react'

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
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { invitationStatusLabel } from '@/helpers/labels/linkStatusLabel'
import { roleLabel } from '@/helpers/labels/roleLabel'
import { memberApi } from '@/lib/access/members'
import { notifyError, notifySuccess } from '@/lib/notify'

export default function MembersPage({
    breadcrumbs,
    listPath,
    apiBase,
    roleOptions,
    canManage,
    actorEmail,
    actorRole,
}) {
    const access = useMemo(() => memberApi(apiBase), [apiBase])
    const [members, setMembers] = useState([])
    const [invitations, setInvitations] = useState([])
    const [loading, setLoading] = useState(true)
    const [memberToRemove, setMemberToRemove] = useState(null)
    const [inviteToRevoke, setInviteToRevoke] = useState(null)
    const [copiedLink, setCopiedLink] = useState(null)

    useEffect(() => {
        load()
    }, [access])

    function load() {
        setLoading(true)
        Promise.all([access.list(), access.invitations()])
            .then(([people, invites]) => {
                setMembers(people ?? [])
                setInvitations(invites ?? [])
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    async function handleRole(member, role) {
        if (!role || role === member.role) return
        setLoading(true)
        try {
            notifySuccess(await access.updateRole(member.uuid, role))
            load()
        } catch (error) {
            notifyError(error)
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
            notifySuccess({ message: 'Convite reenviado' })
            setCopiedLink(updated?.invite_url ?? null)
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

    async function copyLink() {
        if (!copiedLink) return
        try {
            await navigator.clipboard.writeText(copiedLink)
            notifySuccess({ message: 'Link copiado' })
        } catch (error) {
            notifyError(error)
        }
    }

    const pending = invitations.filter((invitation) => invitation.status === 'pending')
    const closed = invitations.filter((invitation) => invitation.status !== 'pending')

    return (
        <>
            <LayoutToolbar
                breadcrumbs={breadcrumbs}
                buttons={canManage ? [{ title: 'Convidar', icon: PlusIcon, to: `${listPath}/convidar` }] : []}
            />

            <LoadingWrapper loading={loading}>
                <section className="mb-10">
                    {!loading && members.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <UsersIcon />
                                </EmptyMedia>
                                <EmptyTitle>Nenhum usuário</EmptyTitle>
                                <EmptyDescription>Convide a primeira pessoa para este contexto.</EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nome</TableHead>
                                    <TableHead>E-mail</TableHead>
                                    <TableHead>Papel</TableHead>
                                    {canManage ? <TableHead>Ações</TableHead> : null}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {members.map((member) => {
                                    const isSelf = member.email === actorEmail
                                    const locked = isSelf || (member.role === 'owner' && actorRole !== 'owner')

                                    return (
                                        <TableRow key={member.uuid}>
                                            <TableCell>{member.name}</TableCell>
                                            <TableCell>{member.email}</TableCell>
                                            <TableCell>
                                                {canManage && !locked ? (
                                                    <Select
                                                        items={roleOptions}
                                                        value={member.role}
                                                        onValueChange={(role) => handleRole(member, role)}
                                                    >
                                                        <SelectTrigger className="w-44">
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
                                                ) : (
                                                    <Badge variant="outline">{roleLabel[member.role] ?? member.role}</Badge>
                                                )}
                                            </TableCell>
                                            {canManage ? (
                                                <TableCell>
                                                    {locked ? null : (
                                                        <ActionButton
                                                            icon={Trash2Icon}
                                                            tooltip="Remover"
                                                            variant="destructive"
                                                            onClick={() => setMemberToRemove(member)}
                                                        />
                                                    )}
                                                </TableCell>
                                            ) : null}
                                        </TableRow>
                                    )
                                })}
                            </TableBody>
                        </Table>
                    )}
                </section>

                <section className="flex flex-col gap-4">
                    <h2 className="text-sm font-medium">Convites</h2>
                    {!loading && invitations.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <MailIcon />
                                </EmptyMedia>
                                <EmptyTitle>Nenhum convite</EmptyTitle>
                                <EmptyDescription>Os convites pendentes e encerrados aparecem aqui.</EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>E-mail</TableHead>
                                    <TableHead>Papel</TableHead>
                                    <TableHead>Situação</TableHead>
                                    <TableHead>Validade</TableHead>
                                    {canManage ? <TableHead>Ações</TableHead> : null}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {[...pending, ...closed].map((invitation) => (
                                    <TableRow key={invitation.uuid}>
                                        <TableCell>{invitation.email}</TableCell>
                                        <TableCell>{roleLabel[invitation.role] ?? invitation.role}</TableCell>
                                        <TableCell>
                                            <Badge variant={invitation.status === 'pending' ? 'secondary' : 'outline'}>
                                                {invitationStatusLabel[invitation.status] ?? invitation.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {invitation.expires_at
                                                ? new Date(invitation.expires_at).toLocaleString('pt-BR')
                                                : '—'}
                                        </TableCell>
                                        {canManage ? (
                                            <TableCell>
                                                {invitation.status === 'pending' ? (
                                                    <div className="flex items-center gap-2">
                                                        <ActionButton
                                                            icon={RefreshCw}
                                                            tooltip="Reenviar"
                                                            onClick={() => handleResend(invitation)}
                                                        />
                                                        <ActionButton
                                                            icon={Trash2Icon}
                                                            tooltip="Cancelar convite"
                                                            variant="destructive"
                                                            onClick={() => setInviteToRevoke(invitation)}
                                                        />
                                                    </div>
                                                ) : null}
                                            </TableCell>
                                        ) : null}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </section>
            </LoadingWrapper>

            {copiedLink ? (
                <div className="mt-6 flex flex-col gap-3 rounded-xl border p-4">
                    <p className="text-sm">O novo link do convite. Ele substitui o anterior.</p>
                    <Input readOnly value={copiedLink} />
                    <div>
                        <Button type="button" variant="outline" onClick={copyLink}>Copiar link</Button>
                    </div>
                </div>
            ) : null}

            <AlertDialog open={!!memberToRemove} onOpenChange={(open) => !open && setMemberToRemove(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Remover pessoa</AlertDialogTitle>
                        <AlertDialogDescription>
                            {`Tem certeza que deseja remover ${memberToRemove?.name || memberToRemove?.email}? O acesso a este contexto é suspenso.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={handleRemove}>
                            Remover
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={!!inviteToRevoke} onOpenChange={(open) => !open && setInviteToRevoke(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Cancelar convite</AlertDialogTitle>
                        <AlertDialogDescription>
                            {`Tem certeza que deseja cancelar o convite de ${inviteToRevoke?.email}? O link deixa de valer.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Voltar</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={handleRevoke}>
                            Cancelar convite
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
