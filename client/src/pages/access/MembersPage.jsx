import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router'
import {
    BanIcon,
    CircleCheckIcon,
    CopyIcon,
    MailIcon,
    PlusIcon,
    SendIcon,
    Trash2Icon,
    UsersIcon,
    XIcon,
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
import { Skeleton } from '@/components/ui/skeleton'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import {
    getInvitations,
    getMembers,
    reactivateMember,
    removeMember,
    resendInvitation,
    revokeInvitation,
    suspendMember,
} from '@/lib/access/members'
import { useSession } from '@/lib/auth/session'
import { copyText, notifyError, notifySuccess } from '@/lib/notify'

const statusLabel = {
    active: 'Ativo',
    suspended: 'Suspenso',
}

const invitationLabel = {
    pending: 'Pendente',
    accepted: 'Aceito',
    declined: 'Recusado',
    revoked: 'Cancelado',
    expired: 'Expirado',
}

/**
 * Usuários e convites da organização ativa.
 *
 * Uma tela só para empresa e posto, e sem coluna de papel: todo membro tem
 * o mesmo acesso. Antes havia quatro arquivos — dois wrappers que só
 * passavam `apiBase` e `roleOptions` diferentes para o mesmo componente.
 *
 * As abas guardam estado na URL (`?aba=convites`), então voltar de um
 * convite recém-criado cai na aba certa.
 */
export default function MembersPage() {
    const { current_organization: organization } = useSession()
    const [searchParams, setSearchParams] = useSearchParams()
    const tab = searchParams.get('aba') === 'convites' ? 'convites' : 'usuarios'

    const basePath = organization?.home_path ?? '/app'

    const [members, setMembers] = useState([])
    const [invitations, setInvitations] = useState([])
    const [loading, setLoading] = useState(true)
    const [confirm, setConfirm] = useState(null)

    const load = useCallback(() => {
        setLoading(true)
        Promise.all([getMembers(), getInvitations()])
            .then(([memberList, invitationList]) => {
                setMembers(memberList ?? [])
                setInvitations(invitationList ?? [])
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [])

    useEffect(() => {
        load()
    }, [load])

    function selectTab(next) {
        const params = new URLSearchParams(searchParams)

        if (next === 'convites') params.set('aba', 'convites')
        else params.delete('aba')

        setSearchParams(params)
    }

    async function run(action, successPrefix) {
        try {
            notifySuccess(await action())
            load()
        } catch (error) {
            notifyError(error)
        } finally {
            setConfirm(null)
        }
    }

    const pendingCount = invitations.filter((item) => item.status === 'pending').length

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: basePath },
                    { text: 'Usuários' },
                ]}
                buttons={[
                    {
                        title: 'Convidar usuário',
                        icon: PlusIcon,
                        to: `${basePath}/usuarios/convidar`,
                    },
                ]}
            />

            <div className="mb-4 flex gap-1 border-b">
                <TabButton active={tab === 'usuarios'} onClick={() => selectTab('usuarios')}>
                    Usuários
                    <Badge variant="secondary">{members.length}</Badge>
                </TabButton>
                <TabButton active={tab === 'convites'} onClick={() => selectTab('convites')}>
                    Convites
                    {pendingCount > 0 ? <Badge variant="secondary">{pendingCount}</Badge> : null}
                </TabButton>
            </div>

            {loading ? (
                <div className="grid gap-2" aria-busy="true">
                    {Array.from({ length: 4 }).map((_, index) => (
                        <Skeleton key={index} className="h-11 w-full" />
                    ))}
                </div>
            ) : tab === 'usuarios' ? (
                <MembersTable members={members} onConfirm={setConfirm} />
            ) : (
                <InvitationsTable
                    invitations={invitations}
                    onConfirm={setConfirm}
                    onResend={(invitation) =>
                        run(() => resendInvitation(invitation.uuid))
                    }
                />
            )}

            <AlertDialog open={!!confirm} onOpenChange={(open) => !open && setConfirm(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{confirm?.title}</AlertDialogTitle>
                        <AlertDialogDescription>{confirm?.description}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            variant={confirm?.destructive ? 'destructive' : 'default'}
                            onClick={() => run(confirm.action)}
                        >
                            {confirm?.confirmLabel}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}

function TabButton({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-current={active ? 'page' : undefined}
            className={`flex items-center gap-2 border-b-2 px-3 pb-2 text-sm transition-colors ${
                active
                    ? 'border-foreground text-foreground font-medium'
                    : 'text-muted-foreground hover:text-foreground border-transparent'
            }`}
        >
            {children}
        </button>
    )
}

function MembersTable({ members, onConfirm }) {
    if (members.length === 0) {
        return (
            <Empty>
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <UsersIcon />
                    </EmptyMedia>
                    <EmptyTitle>Nenhum usuário</EmptyTitle>
                    <EmptyDescription>Convide alguém para trabalhar com você.</EmptyDescription>
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
                    <TableHead>Situação</TableHead>
                    <TableHead className="w-0">Ações</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {members.map((member) => {
                    const isActive = member.status === 'active'

                    return (
                        <TableRow key={member.uuid}>
                            <TableCell>
                                <span className="font-medium">{member.name}</span>
                                {member.is_me ? (
                                    <span className="text-muted-foreground ml-2 text-xs">você</span>
                                ) : null}
                            </TableCell>
                            <TableCell className="text-muted-foreground">{member.email}</TableCell>
                            <TableCell>
                                <Badge variant={isActive ? 'outline' : 'secondary'}>
                                    {statusLabel[member.status] ?? member.status}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                {member.is_me ? (
                                    <span className="text-muted-foreground text-xs">—</span>
                                ) : (
                                    <div className="flex items-center gap-1">
                                        <ActionButton
                                            icon={isActive ? BanIcon : CircleCheckIcon}
                                            tooltip={isActive ? 'Suspender acesso' : 'Reativar acesso'}
                                            variant="outline"
                                            onClick={() =>
                                                onConfirm({
                                                    title: isActive
                                                        ? 'Suspender acesso'
                                                        : 'Reativar acesso',
                                                    description: isActive
                                                        ? `${member.name} deixa de conseguir entrar nesta organização. O vínculo fica guardado e pode ser reativado.`
                                                        : `${member.name} volta a ter acesso a esta organização.`,
                                                    confirmLabel: isActive ? 'Suspender' : 'Reativar',
                                                    destructive: isActive,
                                                    action: () =>
                                                        isActive
                                                            ? suspendMember(member.uuid)
                                                            : reactivateMember(member.uuid),
                                                })
                                            }
                                        />
                                        <ActionButton
                                            icon={Trash2Icon}
                                            tooltip="Remover da organização"
                                            variant="destructive"
                                            onClick={() =>
                                                onConfirm({
                                                    title: 'Remover da organização',
                                                    description: `${member.name} perde o acesso. Para voltar, será preciso um convite novo.`,
                                                    confirmLabel: 'Remover',
                                                    destructive: true,
                                                    action: () => removeMember(member.uuid),
                                                })
                                            }
                                        />
                                    </div>
                                )}
                            </TableCell>
                        </TableRow>
                    )
                })}
            </TableBody>
        </Table>
    )
}

function InvitationsTable({ invitations, onConfirm, onResend }) {
    if (invitations.length === 0) {
        return (
            <Empty>
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <MailIcon />
                    </EmptyMedia>
                    <EmptyTitle>Nenhum convite</EmptyTitle>
                    <EmptyDescription>
                        Os convites enviados aparecem aqui até serem aceitos ou recusados.
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        )
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>E-mail</TableHead>
                    <TableHead>Enviado por</TableHead>
                    <TableHead>Situação</TableHead>
                    <TableHead>Válido até</TableHead>
                    <TableHead className="w-0">Ações</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {invitations.map((invitation) => {
                    const isPending = invitation.status === 'pending'

                    return (
                        <TableRow key={invitation.uuid}>
                            <TableCell className="font-medium">{invitation.email}</TableCell>
                            <TableCell className="text-muted-foreground">
                                {invitation.invited_by ?? '—'}
                            </TableCell>
                            <TableCell>
                                <Badge variant={isPending ? 'outline' : 'secondary'}>
                                    {invitationLabel[invitation.status] ?? invitation.status}
                                </Badge>
                            </TableCell>
                            <TableCell className="text-muted-foreground tabular-nums">
                                {invitation.expires_at
                                    ? new Date(invitation.expires_at).toLocaleDateString('pt-BR')
                                    : '—'}
                            </TableCell>
                            <TableCell>
                                {isPending ? (
                                    <div className="flex items-center gap-1">
                                        <ActionButton
                                            icon={SendIcon}
                                            tooltip="Reenviar convite"
                                            variant="outline"
                                            onClick={() => onResend(invitation)}
                                        />
                                        <ActionButton
                                            icon={XIcon}
                                            tooltip="Cancelar convite"
                                            variant="destructive"
                                            onClick={() =>
                                                onConfirm({
                                                    title: 'Cancelar convite',
                                                    description: `O link enviado para ${invitation.email} deixa de funcionar.`,
                                                    confirmLabel: 'Cancelar convite',
                                                    destructive: true,
                                                    action: () => revokeInvitation(invitation.uuid),
                                                })
                                            }
                                        />
                                    </div>
                                ) : (
                                    <span className="text-muted-foreground text-xs">—</span>
                                )}
                            </TableCell>
                        </TableRow>
                    )
                })}
            </TableBody>
        </Table>
    )
}
