import { useEffect, useState } from "react"
import { UserPlusIcon } from "lucide-react"

import { InvitationLinkDialog } from "@/components/common/InvitationLinkDialog"
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
import { Field, FieldDescription, FieldLabel, FieldLegend, FieldSet } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { formatDate } from "@/helpers/formatters"
import {
    cancelCompanyInvitation,
    createCompanyInvitation,
    getCompanyInvitations,
    renewCompanyInvitation,
} from "@/lib/system/companies"
import { notifyError, notifySuccess } from "@/lib/notify"

/**
 * Bloco "Administradores" da edição da empresa: quem já é admin e os convites
 * pendentes. A Logiv convida, copia um novo link ou cancela; não mexe em contas.
 */
export function CompanyAdmins({ companyUuid, admins, onBusy }) {
    const [invitations, setInvitations] = useState([])
    const [inviteOpen, setInviteOpen] = useState(false)
    const [email, setEmail] = useState("")
    const [created, setCreated] = useState(null)
    const [invitationToRenew, setInvitationToRenew] = useState(null)

    useEffect(() => {
        getCompanyInvitations(companyUuid).then(setInvitations).catch(notifyError)
    }, [companyUuid])

    async function run(action) {
        onBusy(true)

        try {
            await action()
            setInvitations(await getCompanyInvitations(companyUuid))
        } catch (error) {
            notifyError(error)
        } finally {
            onBusy(false)
        }
    }

    function handleInvite(event) {
        event.preventDefault()
        run(async () => {
            const result = await createCompanyInvitation(companyUuid, email)
            setInviteOpen(false)
            setCreated(result)
        })
    }

    function handleRenew() {
        const invitation = invitationToRenew
        setInvitationToRenew(null)
        run(async () => setCreated(await renewCompanyInvitation(companyUuid, invitation.uuid)))
    }

    function handleCancel(invitation) {
        run(async () => notifySuccess(await cancelCompanyInvitation(companyUuid, invitation.uuid)))
    }

    return (
        <FieldSet>
            <div className="flex items-start gap-4">
                <div className="flex flex-col gap-1.5">
                    <FieldLegend variant="label">Administradores</FieldLegend>
                    <FieldDescription>
                        Quem administra a empresa e os convites que ainda não foram aceitos.
                    </FieldDescription>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    className="ml-auto"
                    onClick={() => {
                        setEmail("")
                        setInviteOpen(true)
                    }}
                >
                    <UserPlusIcon data-icon="inline-start" />
                    Convidar administrador
                </Button>
            </div>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nome</TableHead>
                        <TableHead>E-mail</TableHead>
                        <TableHead>Situação</TableHead>
                        <TableHead>Ações</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {admins.map((admin) => (
                        <TableRow key={admin.uuid}>
                            <TableCell>{admin.name}</TableCell>
                            <TableCell>{admin.email}</TableCell>
                            <TableCell>
                                <Badge variant="secondary">Administrador</Badge>
                            </TableCell>
                            <TableCell />
                        </TableRow>
                    ))}
                    {invitations.map((invitation) => (
                        <TableRow key={invitation.uuid}>
                            <TableCell className="text-muted-foreground">Convite pendente</TableCell>
                            <TableCell>{invitation.email}</TableCell>
                            <TableCell>
                                {invitation.expired ? (
                                    <Badge variant="outline">Expirado</Badge>
                                ) : (
                                    `Expira em ${formatDate(invitation.expires_at)}`
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
                    {admins.length === 0 && invitations.length === 0 ? (
                        <TableRow>
                            <TableCell colSpan={4} className="text-muted-foreground">
                                Nenhum administrador nem convite pendente. Use “Convidar administrador”.
                            </TableCell>
                        </TableRow>
                    ) : null}
                </TableBody>
            </Table>

            <Dialog open={inviteOpen} onOpenChange={setInviteOpen}>
                <DialogContent>
                    <form onSubmit={handleInvite} className="flex flex-col gap-6">
                        <DialogHeader>
                            <DialogTitle>Convidar administrador</DialogTitle>
                            <DialogDescription>
                                Vamos gerar um link de convite. A pessoa cria a conta, ou entra com a que já tem, e vira administradora da empresa.
                            </DialogDescription>
                        </DialogHeader>
                        <Field>
                            <FieldLabel htmlFor="admin-invite-email">E-mail</FieldLabel>
                            <Input
                                id="admin-invite-email"
                                type="email"
                                placeholder="Ex: ana@empresa.com.br"
                                value={email}
                                onChange={(event) => setEmail(event.target.value)}
                                required
                            />
                        </Field>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setInviteOpen(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit">Gerar convite</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <InvitationLinkDialog created={created} onClose={() => setCreated(null)} />

            <AlertDialog open={!!invitationToRenew} onOpenChange={(open) => !open && setInvitationToRenew(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Copiar novo link</AlertDialogTitle>
                        <AlertDialogDescription>O link anterior deixa de funcionar. Continuar?</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={handleRenew}>Gerar novo link</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </FieldSet>
    )
}
