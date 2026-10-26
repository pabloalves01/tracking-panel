import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { formatDate } from '@/helpers/formatters'
import { invitationLink } from '@/lib/auth/invitations'
import { notifySuccess } from '@/lib/notify'
import { toast } from 'sonner'

/**
 * Janela do link de convite. Usada depois de convidar e de "Copiar novo link".
 * `created`: `{ data: convite, token }`, como a API devolve. Sem ele, fica fechada.
 */
export function InvitationLinkDialog({ created, onClose }) {
    const link = created ? invitationLink(created.token) : ''

    async function copy() {
        try {
            await navigator.clipboard.writeText(link)
            notifySuccess('Link copiado')
        } catch {
            toast.error('Não foi possível copiar', {
                description: 'Selecione o link e copie manualmente.',
            })
        }
    }

    return (
        <Dialog open={!!created} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Link do convite</DialogTitle>
                    <DialogDescription>
                        {created
                            ? `Convite criado para ${created.data.email}. Envie este link para a pessoa convidada. Ele vale até ${formatDate(created.data.expires_at)} e só funciona com este e-mail.`
                            : null}
                    </DialogDescription>
                </DialogHeader>
                <Input readOnly value={link} onFocus={(event) => event.target.select()} aria-label="Link do convite" />
                <p className="text-xs text-muted-foreground">
                    O link aparece só agora. Se perder, use “Copiar novo link” na lista de convites.
                </p>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Fechar
                    </Button>
                    <Button type="button" onClick={copy}>
                        Copiar link
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
