import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'

import { AccountGate } from '@/components/auth/AccountGate'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
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
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { invitationStatusLabel } from '@/helpers/labels/linkStatusLabel'
import { logout, me } from '@/lib/auth/auth'
import { acceptInvitation, declineInvitation, previewInvitation } from '@/lib/auth/invitations'
import { notifyError, notifySuccess } from '@/lib/notify'

/**
 * A tela que o convidado abre pelo link do e-mail.
 *
 *   logado na conta convidada → aceitar
 *   logado em outra conta     → sair e seguir como o e-mail convidado
 *   deslogado                 → AccountGate com o e-mail travado → aceitar
 *
 * No último caso o preview já diz se o e-mail tem conta, então quem tem vai
 * direto para a senha atual — sem código e sem formulário de cadastro. Antes
 * a tela mostrava nome e senha para todo mundo, e só depois de preenchido
 * mandava quem já tinha conta para o login.
 *
 * O convite não cria conta: quem não tem passa pelo cadastro de conta do
 * AccountGate e aceita em seguida, já logado.
 */
export default function InvitationPage() {
    const { token } = useParams()
    const [loading, setLoading] = useState(true)
    const [invite, setInvite] = useState(null)
    const [user, setUser] = useState(null)
    const [confirmingDecline, setConfirmingDecline] = useState(false)

    useEffect(() => {
        let active = true

        async function load() {
            try {
                const preview = await previewInvitation(token)
                if (!active) return
                setInvite(preview)

                if (localStorage.getItem('token')) {
                    try {
                        const session = await me()
                        if (active) setUser(session.user)
                    } catch {
                        localStorage.removeItem('token')
                    }
                }
            } catch (requestError) {
                if (active) notifyError(requestError)
            } finally {
                if (active) setLoading(false)
            }
        }

        load()

        return () => {
            active = false
        }
    }, [token])

    const pending = invite?.status === 'pending'
    const sameEmail = Boolean(
        user?.email && invite?.email && user.email.toLowerCase() === invite.email.toLowerCase()
    )

    async function accept() {
        setLoading(true)

        try {
            const response = await acceptInvitation(token)
            notifySuccess(response)
            window.location.assign(
                response.redirect_to ?? (invite.organization_type === 'fuel_station' ? '/posto' : '/app')
            )
        } catch (requestError) {
            notifyError(requestError)
            setLoading(false)
        }
    }

    /** Recém-logado pelo AccountGate: aceita na sequência. */
    async function handleAuthenticated(session) {
        setUser(session.user)
        await accept()
    }

    async function switchAccount() {
        setLoading(true)

        try {
            await logout()
        } catch {
            localStorage.removeItem('token')
        }

        setUser(null)
        setLoading(false)
    }

    async function handleDecline() {
        setConfirmingDecline(false)
        setLoading(true)

        try {
            notifySuccess(await declineInvitation(token))
            setInvite((current) => ({ ...current, status: 'declined' }))
        } catch (requestError) {
            notifyError(requestError)
        } finally {
            setLoading(false)
        }
    }

    return (
        <LoadingWrapper loading={loading}>
            <div className="flex min-h-svh flex-col gap-4 p-6 md:p-10">
                <div className="flex items-center justify-between">
                    <Link to="/auth/login" className="flex items-center gap-2 font-medium">
                        <img src="/brand/logo-dark.svg" alt="Logiv" className="h-8 w-auto dark:hidden" />
                        <img src="/brand/logo-mono.svg" alt="" className="hidden h-8 w-auto dark:block" />
                    </Link>
                    <ThemeToggle />
                </div>

                <div className="flex flex-1 items-center justify-center">
                    <div className="w-full max-w-md">
                        {!invite ? (
                            loading ? null : (
                                <Alert>
                                    <AlertTitle>Convite não encontrado</AlertTitle>
                                    <AlertDescription>
                                        O link pode ter expirado ou já ter sido usado. Peça um convite novo a
                                        quem administra a organização.
                                    </AlertDescription>
                                </Alert>
                            )
                        ) : (
                            <div className="flex flex-col gap-6">
                                {/* Quem convidou, para onde e para qual e-mail: é o que
                                    decide se a pessoa reconhece o convite como legítimo. */}
                                <Card>
                                    <CardContent className="flex flex-col gap-1 py-5 text-center">
                                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                            {invite.organization_type === 'fuel_station' ? 'Posto' : 'Empresa'}
                                        </p>
                                        <h1 className="text-xl font-semibold">{invite.organization}</h1>
                                        <p className="text-sm text-muted-foreground">
                                            {invite.invited_by
                                                ? `${invite.invited_by} convidou você`
                                                : 'Você foi convidado'}{' '}
                                            como <span className="text-foreground">{invite.email}</span>
                                        </p>
                                    </CardContent>
                                </Card>

                                {!pending ? (
                                    <>
                                        <Alert>
                                            <AlertTitle>
                                                {invitationStatusLabel[invite.status] ?? 'Convite encerrado'}
                                            </AlertTitle>
                                            <AlertDescription>
                                                Este convite não está mais pendente. Peça um novo a quem
                                                administra a organização.
                                            </AlertDescription>
                                        </Alert>
                                        <Button variant="outline" nativeButton={false} render={<Link to="/auth/login" />}>
                                            Ir para o login
                                        </Button>
                                    </>
                                ) : null}

                                {pending && user && sameEmail ? (
                                    <div className="flex flex-col gap-2">
                                        <Button type="button" onClick={accept} disabled={loading}>
                                            Aceitar convite
                                        </Button>
                                        <p className="text-center text-sm text-muted-foreground">
                                            Você entra em {invite.organization} com a conta que já usa.
                                        </p>
                                    </div>
                                ) : null}

                                {pending && user && !sameEmail ? (
                                    <div className="flex flex-col gap-3">
                                        <Alert>
                                            <AlertTitle>Você está em outra conta</AlertTitle>
                                            <AlertDescription>
                                                Entrou como {user.email}, e este convite é para {invite.email}.
                                            </AlertDescription>
                                        </Alert>
                                        <Button type="button" onClick={switchAccount}>
                                            Sair e continuar como {invite.email}
                                        </Button>
                                    </div>
                                ) : null}

                                {pending && !user ? (
                                    <AccountGate
                                        email={invite.email}
                                        accountExists={invite.account_exists}
                                        title="Confirme seu e-mail"
                                        description="Enviaremos um código para criar o seu acesso."
                                        forgotBack={`/auth/convite/${token}`}
                                        onAuthenticated={handleAuthenticated}
                                    />
                                ) : null}

                                {/* Recusar é definitivo — o token morre junto —, então fica
                                    longe da ação principal e pede confirmação. */}
                                {pending ? (
                                    <div className="flex justify-center border-t pt-4 text-sm">
                                        <button
                                            type="button"
                                            className="text-muted-foreground underline-offset-4 hover:underline"
                                            onClick={() => setConfirmingDecline(true)}
                                        >
                                            Recusar convite
                                        </button>
                                    </div>
                                ) : null}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <AlertDialog open={confirmingDecline} onOpenChange={setConfirmingDecline}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Recusar o convite?</AlertDialogTitle>
                        <AlertDialogDescription>
                            O link para de valer. Para entrar em {invite?.organization} depois, será
                            preciso pedir um convite novo.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Voltar</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDecline}>Recusar convite</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </LoadingWrapper>
    )
}
