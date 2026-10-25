import { useContext, useEffect, useState } from "react"
import { EllipsisIcon, UserPlusIcon, UsersIcon } from "lucide-react"
import { useNavigate } from "react-router"

import { InvitationLinkDialog } from "@/components/common/InvitationLinkDialog"
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { formatDate } from "@/helpers/formatters"
import { roleLabel } from "@/helpers/labels"
import {
    cancelInvitation,
    createInvitation,
    getInvitations,
    getMembers,
    removeMember,
    renewInvitation,
    updateMemberRole,
} from "@/lib/access/users"
import { SessionContext } from "@/lib/auth/session"
import { StationSessionContext } from "@/lib/auth/stationSession"
import { notifyError, notifySuccess } from "@/lib/notify"

const roles = [
    { value: "member", label: roleLabel.member },
    { value: "admin", label: roleLabel.admin },
]

/**
 * Lê membros e convites pendentes. Não mexe no estado da tela.
 */
async function readUsers(scope) {
    try {
        const [members, invitations] = await Promise.all([getMembers(scope), getInvitations(scope)])
        return { members, invitations, error: null }
    } catch (error) {
        return { members: [], invitations: [], error }
    }
}

/**
 * Usuários da empresa (`/app/usuarios`) ou do posto (`/posto/usuarios`).
 * Só o admin chega aqui; a API devolve 403 para os demais.
 */
export default function UsersPage({ scope }) {
    const navigate = useNavigate()
    const session = useContext(SessionContext)
    const stationSession = useContext(StationSessionContext)
    const isStation = scope === "station"
    const home = isStation ? "/posto" : "/app"
    const place = isStation ? stationSession?.station : session?.currentCompany
    const placeName = isStation
        ? place?.trade_name || place?.corporate_name
        : place?.trade_name || place?.legal_name
    const ofPlace = isStation ? "do posto" : "da empresa"

    const [loading, setLoading] = useState(true)
    const [members, setMembers] = useState([])
    const [invitations, setInvitations] = useState([])
    const [forbidden, setForbidden] = useState(null)
    const [inviteOpen, setInviteOpen] = useState(false)
    const [email, setEmail] = useState("")
    const [role, setRole] = useState("member")
    const [created, setCreated] = useState(null)
    const [memberToRemove, setMemberToRemove] = useState(null)
    const [invitationToRenew, setInvitationToRenew] = useState(null)

    function applyState(state) {
        setMembers(state.members)
        setInvitations(state.invitations)
        setForbidden(state.error?.response?.status === 403 ? state.error.response.data?.message : null)
        if (state.error && state.error.response?.status !== 403) {
            notifyError(state.error)
        }
        setLoading(false)
    }

    // Quem chama já ligou o loading.
    function load() {
        return readUsers(scope).then(applyState)
    }

    useEffect(() => {
        readUsers(scope).then(applyState)
    }, [scope])

    function openInvite() {
        setEmail("")
        setRole("member")
        setInviteOpen(true)
    }

    async function handleInvite(event) {
        event.preventDefault()
        setLoading(true)

        try {
            const result = await createInvitation(scope, { email, role })
            setInviteOpen(false)
            setCreated(result)
            await load()
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    async function handleRole(member, nextRole) {
        setLoading(true)

        try {
            const response = await updateMemberRole(scope, member.uuid, nextRole)
            notifySuccess(response)
            if (member.is_you) {
                // Deixou de ser admin: o menu e esta tela não valem mais.
                window.location.assign(home)
                return
            }
            await load()
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    async function handleRemove() {
        const member = memberToRemove
        setMemberToRemove(null)
        setLoading(true)

        try {
            const response = await removeMember(scope, member.uuid)
            notifySuccess(response)
            if (member.is_you) {
                navigate("/auth/contextos", { replace: true })
                return
            }
            await load()
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    async function handleRenew() {
        const invitation = invitationToRenew
        setInvitationToRenew(null)
        setLoading(true)

        try {
            const result = await renewInvitation(scope, invitation.uuid)
            setCreated(result)
            await load()
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    async function handleCancel(invitation) {
        setLoading(true)

        try {
            const response = await cancelInvitation(scope, invitation.uuid)
            notifySuccess(response)
            await load()
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    const breadcrumbs = [{ text: "Início", to: home }, { text: "Usuários" }]

    if (forbidden) {
        return (
            <>
                <LayoutToolbar breadcrumbs={breadcrumbs} />
                <Empty>
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <UsersIcon />
                        </EmptyMedia>
                        <EmptyTitle>Usuários</EmptyTitle>
                        <EmptyDescription>{forbidden}</EmptyDescription>
                    </EmptyHeader>
                </Empty>
            </>
        )
    }

    return (
        <>
            <LayoutToolbar
                breadcrumbs={breadcrumbs}
                buttons={[{ title: "Convidar usuário", icon: UserPlusIcon, onClick: openInvite }]}
            />

            <LoadingWrapper loading={loading}>
                <div className="flex flex-col gap-8">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nome</TableHead>
                                <TableHead>E-mail</TableHead>
                                <TableHead>Papel</TableHead>
                                <TableHead>Entrou em</TableHead>
                                <TableHead className="w-12" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {members.map((member) => (
                                <TableRow key={member.uuid}>
                                    <TableCell>
                                        {member.name}
                                        {member.is_you ? <span className="text-muted-foreground"> (você)</span> : null}
                                    </TableCell>
                                    <TableCell>{member.email}</TableCell>
                                    <TableCell>
                                        <Badge variant={member.role === "admin" ? "secondary" : "outline"}>
                                            {roleLabel[member.role] ?? member.role}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{formatDate(member.joined_at)}</TableCell>
                                    <TableCell>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                render={
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        aria-label={`Ações de ${member.name}`}
                                                    />
                                                }
                                            >
                                                <EllipsisIcon />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="w-52">
                                                <DropdownMenuGroup>
                                                    <DropdownMenuItem
                                                        onClick={() => handleRole(member, member.role === "admin" ? "member" : "admin")}
                                                    >
                                                        {member.role === "admin" ? "Tornar membro" : "Tornar administrador"}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onClick={() => setMemberToRemove(member)}
                                                    >
                                                        {member.is_you ? `Sair ${ofPlace}` : "Remover"}
                                                    </DropdownMenuItem>
                                                </DropdownMenuGroup>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    {invitations.length > 0 ? (
                        <section className="flex flex-col gap-3">
                            <h2 className="text-sm font-medium">Convites pendentes</h2>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>E-mail</TableHead>
                                        <TableHead>Papel</TableHead>
                                        <TableHead>Convidado por</TableHead>
                                        <TableHead>Expira em</TableHead>
                                        <TableHead>Ações</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {invitations.map((invitation) => (
                                        <TableRow key={invitation.uuid}>
                                            <TableCell>{invitation.email}</TableCell>
                                            <TableCell>{roleLabel[invitation.role] ?? invitation.role}</TableCell>
                                            <TableCell>{invitation.invited_by ?? "—"}</TableCell>
                                            <TableCell>
                                                {invitation.expired ? (
                                                    <Badge variant="outline">Expirado</Badge>
                                                ) : (
                                                    formatDate(invitation.expires_at)
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => setInvitationToRenew(invitation)}
                                                    >
                                                        Copiar novo link
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleCancel(invitation)}
                                                    >
                                                        Cancelar convite
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </section>
                    ) : null}
                </div>
            </LoadingWrapper>

            <Dialog open={inviteOpen} onOpenChange={setInviteOpen}>
                <DialogContent>
                    <form onSubmit={handleInvite} className="flex flex-col gap-6">
                        <DialogHeader>
                            <DialogTitle>Convidar usuário</DialogTitle>
                            <DialogDescription>
                                Vamos gerar um link de convite para você enviar à pessoa.
                            </DialogDescription>
                        </DialogHeader>
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="invite-email">E-mail</FieldLabel>
                                <Input
                                    id="invite-email"
                                    type="email"
                                    placeholder="Ex: ana@empresa.com.br"
                                    value={email}
                                    onChange={(event) => setEmail(event.target.value)}
                                    required
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="invite-role">Papel</FieldLabel>
                                <Select items={roles} value={role} onValueChange={setRole}>
                                    <SelectTrigger id="invite-role" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {roles.map((item) => (
                                                <SelectItem key={item.value} value={item.value}>
                                                    {item.label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                        </FieldGroup>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setInviteOpen(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={loading}>
                                Gerar convite
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <InvitationLinkDialog created={created} onClose={() => setCreated(null)} />

            <AlertDialog open={!!memberToRemove} onOpenChange={(open) => !open && setMemberToRemove(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {memberToRemove?.is_you ? `Sair ${ofPlace}` : "Remover usuário"}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {memberToRemove?.is_you
                                ? `Sair ${isStation ? "do" : "da"} ${placeName}? Você perde o acesso na hora.`
                                : `Remover ${memberToRemove?.name} ${isStation ? "do" : "da"} ${placeName}? O acesso é perdido na hora.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={handleRemove}>
                            {memberToRemove?.is_you ? "Sair" : "Remover"}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={!!invitationToRenew} onOpenChange={(open) => !open && setInvitationToRenew(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Copiar novo link</AlertDialogTitle>
                        <AlertDialogDescription>
                            O link anterior deixa de funcionar. Continuar?
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={handleRenew}>Gerar novo link</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    )
}
