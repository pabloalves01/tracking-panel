import { CopyIcon, MailIcon } from 'lucide-react'

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '@/components/ui/input-group'
import { copyText } from '@/lib/notify'

/**
 * Mostra o link de um convite recém-criado.
 *
 * O token só existe em claro neste instante — o banco guarda o sha256 e a
 * listagem nunca o devolve. Então esta é a única chance de capturá-lo, e por
 * isso a tela não navega para longe antes de a pessoa fechar o diálogo.
 *
 * O botão de copiar fica aqui, e não no fim do submit, porque a escrita na
 * área de transferência exige o gesto do usuário: depois do `await` da
 * requisição o navegador recusa.
 */
export function InviteLinkDialog({ invite, onClose }) {
    if (!invite?.invite_url) return null

    return (
        <AlertDialog open onOpenChange={(open) => !open && onClose?.()}>
            <AlertDialogContent className="sm:max-w-lg">
                <AlertDialogHeader>
                    <AlertDialogTitle>Convite enviado</AlertDialogTitle>
                    <AlertDialogDescription>
                        Mandamos o link para <strong className="text-foreground">{invite.email}</strong>.
                        Se preferir mandar por outro canal, copie agora — por segurança o link
                        não fica guardado e não aparece de novo na listagem.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <InputGroup>
                    <InputGroupInput
                        readOnly
                        value={invite.invite_url}
                        aria-label="Link do convite"
                        onFocus={(event) => event.target.select()}
                    />
                    <InputGroupAddon align="inline-end">
                        <InputGroupButton
                            type="button"
                            size="icon-xs"
                            aria-label="Copiar link"
                            onClick={() => copyText(invite.invite_url, 'Link do convite copiado')}
                        >
                            <CopyIcon />
                        </InputGroupButton>
                    </InputGroupAddon>
                </InputGroup>

                <p className="text-xs text-muted-foreground">
                    <MailIcon className="mr-1 inline size-3.5 align-[-2px]" />
                    Vale por 7 dias. Se expirar, reenvie pela lista de convites.
                </p>

                <AlertDialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => copyText(invite.invite_url, 'Link do convite copiado')}
                    >
                        Copiar link
                    </Button>
                    <AlertDialogAction onClick={onClose}>Concluir</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    )
}
