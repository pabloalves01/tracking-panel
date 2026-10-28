import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'

import { CodeStep } from '@/components/auth/CodeStep'
import { InputPassword } from '@/components/common/inputs/InputPassword'
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
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { invitationStatusLabel } from '@/helpers/labels/linkStatusLabel'
import { logout, me } from '@/lib/auth/auth'
import { acceptInvitation, declineInvitation, previewInvitation, registerInvitation } from '@/lib/auth/invitations'
import { sendVerificationCode, verificationError } from '@/lib/auth/verification'
import { notifyError, notifySuccess } from '@/lib/notify'

/**
 * A tela que o convidado abre pelo link do e-mail.
 *
 * Ela atende quatro situações diferentes com uma ação principal cada, e é
 * aí que estava o problema: o botão dizia "entrar" mesmo quando o e-mail do
 * convite ainda não tinha conta, e levava a um cadastro. O rótulo agora sai
 * de `account_exists`, que a API já devolve no preview.
 *
 * Recusar é definitivo — o token morre junto — então sai do meio das ações
 * e pede confirmação.
 */
export default function InvitationPage() {
    const { token } = useParams()
    const navigate = useNavigate()
    const [loading, setLoading] = useState(true)
    const [invite, setInvite] = useState(null)
    const [user, setUser] = useState(null)
    const [step, setStep] = useState('account')
    const [name, setName] = useState('')
    const [password, setPassword] = useState('')
    const [passwordConfirmation, setPasswordConfirmation] = useState('')
    const [retryAfter, setRetryAfter] = useState(60)
    const [error, setError] = useState('')
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
                        const current = await me()
                        if (active) setUser(current.user ?? current)
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
    const accountExists = Boolean(invite?.account_exists)
    const sameEmail = user?.email && invite?.email && user.email.toLowerCase() === invite.email.toLowerCase()
    const loginRedirect = `/auth/login?redirect=${encodeURIComponent(`/auth/convite/${token}`)}&email=${encodeURIComponent(invite?.email ?? '')}&email_locked=1`

    /**
     * A API devolve `redirect_to` com o caminho da organização que acabou de
     * ser vinculada — não é preciso adivinhar pelo tipo.
     */
    function goAfterAccept(response) {
        window.location.assign(
            response?.redirect_to
                ?? (invite?.organization_type === 'fuel_station' ? '/posto' : '/app')
        )
    }

    async function sendCode() {
        const response = await sendVerificationCode({
            email: invite.email,
            purpose: 'invitation_register',
            token,
        })
        const seconds = Number(response.retry_after) || 0
        setRetryAfter(seconds > 0 ? seconds : 60)

        return response
    }

    /** Sai da conta atual e segue para onde o e-mail do convite de fato leva. */
    async function switchToInviteEmail() {
        setLoading(true)

        try {
            await logout()
        } catch {
            localStorage.removeItem('token')
        }

        setUser(null)
        setStep('account')

        if (accountExists) {
            navigate(loginRedirect)
            return
        }

        setLoading(false)
    }

    async function handleRegisterDetails(event) {
        event.preventDefault()
        setError('')

        if (password.length < 8) {
            setError('A senha deve ter no mínimo 8 caracteres')
            return
        }

        if (password !== passwordConfirmation) {
            setError('A confirmação da senha não confere')
            return
        }

        setLoading(true)

        try {
            const response = await sendCode()

            if (response.account_exists) {
                navigate(loginRedirect)
                return
            }

            if (response.sent === false) {
                setError(response.message || 'Aguarde para reenviar o código.')
                return
            }

            setStep('code')
        } catch (requestError) {
            setError(verificationError(requestError, 'Não foi possível enviar o código.'))
        } finally {
            setLoading(false)
        }
    }

    async function handleCode(code) {
        setLoading(true)
        setError('')

        try {
            const response = await registerInvitation(token, {
                name,
                password,
                password_confirmation: passwordConfirmation,
                code,
            })
            notifySuccess(response)
            goAfterAccept(response)
        } catch (requestError) {
            setError(verificationError(requestError, 'Não foi possível confirmar o código.'))
            setLoading(false)
        }
    }

    async function handleAccept() {
        setLoading(true)

        try {
            const response = await acceptInvitation(token)
            notifySuccess(response)
            goAfterAccept(response)
        } catch (requestError) {
            notifyError(requestError)
            setLoading(false)
        }
    }

    async function handleDecline() {
        setConfirmingDecline(false)
        setLoading(true)

        try {
            const response = await declineInvitation(token)
            notifySuccess(response)
            setInvite((current) => ({ ...current, status: 'declined' }))
        } catch (requestError) {
            notifyError(requestError)
        } finally {
            setLoading(false)
        }
    }

    return (
        <LoadingWrapper loading={loading && step !== 'code'}>
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
                            <Alert>
                                <AlertTitle>Convite não encontrado</AlertTitle>
                                <AlertDescription>
                                    O link pode ter expirado ou já ter sido usado. Peça um convite novo a
                                    quem administra a organização.
                                </AlertDescription>
                            </Alert>
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
                                        <Button variant="outline" render={<Link to="/auth/login" />}>
                                            Ir para o login
                                        </Button>
                                    </>
                                ) : null}

                                {pending && user && sameEmail ? (
                                    <div className="flex flex-col gap-3">
                                        <Button type="button" onClick={handleAccept} disabled={loading}>
                                            Aceitar convite
                                        </Button>
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
                                        {/* O rótulo segue o que de fato acontece: sem conta
                                            para o e-mail do convite, o próximo passo é criá-la. */}
                                        <Button type="button" onClick={switchToInviteEmail}>
                                            {accountExists
                                                ? `Sair e entrar como ${invite.email}`
                                                : `Sair e criar a conta de ${invite.email}`}
                                        </Button>
                                    </div>
                                ) : null}

                                {pending && !user && step === 'account' ? (
                                    <form className="flex flex-col gap-6" onSubmit={handleRegisterDetails}>
                                        <FieldGroup>
                                            <Field>
                                                <FieldLabel htmlFor="invite-email">E-mail</FieldLabel>
                                                <Input
                                                    id="invite-email"
                                                    type="email"
                                                    value={invite.email}
                                                    readOnly
                                                    disabled
                                                    autoComplete="email"
                                                />
                                            </Field>
                                            <Field>
                                                <FieldLabel htmlFor="invite-name">Nome</FieldLabel>
                                                <Input
                                                    id="invite-name"
                                                    value={name}
                                                    autoComplete="name"
                                                    required
                                                    autoFocus
                                                    onChange={(event) => setName(event.target.value)}
                                                />
                                            </Field>
                                            <Field>
                                                <FieldLabel htmlFor="invite-password">Senha</FieldLabel>
                                                <InputPassword
                                                    id="invite-password"
                                                    value={password}
                                                    autoComplete="new-password"
                                                    onChange={setPassword}
                                                    placeholder="Mínimo de 8 caracteres"
                                                />
                                            </Field>
                                            <Field>
                                                <FieldLabel htmlFor="invite-password-confirmation">
                                                    Confirmação da senha
                                                </FieldLabel>
                                                <InputPassword
                                                    id="invite-password-confirmation"
                                                    value={passwordConfirmation}
                                                    autoComplete="new-password"
                                                    onChange={setPasswordConfirmation}
                                                    placeholder="Repita a senha"
                                                />
                                            </Field>
                                            {error ? (
                                                <p className="text-sm text-destructive" role="alert">{error}</p>
                                            ) : null}
                                            <Button type="submit" disabled={loading}>
                                                Criar conta e continuar
                                            </Button>
                                        </FieldGroup>
                                    </form>
                                ) : null}

                                {pending && !user && step === 'code' ? (
                                    <CodeStep
                                        email={invite.email}
                                        retryAfter={retryAfter}
                                        submitting={loading}
                                        error={error}
                                        onSubmit={handleCode}
                                        onResend={async () => {
                                            setError('')
                                            try {
                                                const response = await sendCode()

                                                if (response.account_exists) {
                                                    navigate(loginRedirect)
                                                    return 0
                                                }

                                                if (response.sent === false) {
                                                    setError(response.message || 'Aguarde para reenviar o código.')
                                                }

                                                return Number(response.retry_after) || 0
                                            } catch (requestError) {
                                                setError(verificationError(requestError, 'Não foi possível reenviar o código.'))
                                                return 0
                                            }
                                        }}
                                        onChangeEmail={() => {
                                            setStep('account')
                                            setError('')
                                        }}
                                    />
                                ) : null}

                                {/* Ações secundárias, longe do botão principal. Recusar é
                                    definitivo: o token morre junto e não há como desfazer. */}
                                {pending ? (
                                    <div className="flex flex-col items-center gap-2 border-t pt-4 text-sm">
                                        {!user && step === 'account' ? (
                                            <Link to={loginRedirect} className="text-muted-foreground underline-offset-4 hover:underline">
                                                Já tenho conta com este e-mail
                                            </Link>
                                        ) : null}
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
