import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'

import { CodeStep } from '@/components/auth/CodeStep'
import { InputPassword } from '@/components/common/inputs/InputPassword'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { invitationStatusLabel } from '@/helpers/labels/linkStatusLabel'
import { roleLabel } from '@/helpers/labels/roleLabel'
import { logout, me } from '@/lib/auth/auth'
import { acceptInvitation, declineInvitation, previewInvitation, registerInvitation } from '@/lib/auth/invitations'
import { sendVerificationCode, verificationError } from '@/lib/auth/verification'
import { notifyError, notifySuccess } from '@/lib/notify'

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
                        if (active) setUser(current)
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

    function goAfterAccept(organizationType) {
        window.location.assign(organizationType === 'fuel_station' ? '/posto' : '/app')
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

    function openInviteLogin() {
        navigate(loginRedirect)
    }

    async function switchToInviteEmail() {
        setLoading(true)

        try {
            await logout()
        } catch {
            localStorage.removeItem('token')
        }

        navigate(loginRedirect)
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
                openInviteLogin()
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
            goAfterAccept(invite?.organization?.type)
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
            goAfterAccept(invite?.organization?.type)
        } catch (requestError) {
            notifyError(requestError)
            setLoading(false)
        }
    }

    async function handleDecline() {
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

    const pending = invite?.status === 'pending'
    const sameEmail = user?.email && invite?.email && user.email.toLowerCase() === invite.email.toLowerCase()
    const loginRedirect = `/auth/login?redirect=${encodeURIComponent(`/auth/convite/${token}`)}&email=${encodeURIComponent(invite?.email ?? '')}&email_locked=1`

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
                            <p className="text-center text-sm text-muted-foreground">Convite não encontrado.</p>
                        ) : (
                            <div className="flex flex-col gap-6">
                                <div className="flex flex-col items-center gap-1 text-center">
                                    <h1 className="text-2xl font-bold">Convite</h1>
                                    <p className="text-sm text-muted-foreground">
                                        {invite.organization?.name} convidou {invite.email} como {roleLabel[invite.role] ?? invite.role}.
                                    </p>
                                </div>

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
                                                <FieldLabel htmlFor="invite-password-confirmation">Confirmação da senha</FieldLabel>
                                                <InputPassword
                                                    id="invite-password-confirmation"
                                                    value={passwordConfirmation}
                                                    autoComplete="new-password"
                                                    onChange={setPasswordConfirmation}
                                                    placeholder="Repita a senha"
                                                />
                                            </Field>
                                            {error ? <p className="text-sm text-destructive" role="alert">{error}</p> : null}
                                            <Button type="submit" disabled={loading}>Continuar</Button>
                                            <Button variant="outline" render={<Link to={loginRedirect} />}>
                                                Já tenho conta
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
                                                    openInviteLogin()
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

                                {pending && user && sameEmail ? (
                                    <Button type="button" onClick={handleAccept}>Aceitar convite</Button>
                                ) : null}

                                {pending && user && !sameEmail ? (
                                    <Alert>
                                        <AlertTitle>Conta diferente</AlertTitle>
                                        <AlertDescription>
                                            Você entrou como {user.email}. Este convite é para {invite.email}. Saia e entre com o e-mail do convite.
                                        </AlertDescription>
                                        <Button className="mt-3" type="button" onClick={switchToInviteEmail}>
                                            Entrar com o e-mail do convite
                                        </Button>
                                    </Alert>
                                ) : null}

                                {pending ? (
                                    <Button type="button" variant="outline" onClick={handleDecline}>
                                        Recusar convite
                                    </Button>
                                ) : (
                                    <Alert>
                                        <AlertTitle>{invitationStatusLabel[invite.status] ?? 'Convite encerrado'}</AlertTitle>
                                        <AlertDescription>
                                            Este convite não está mais pendente.
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <Button type="button" variant="ghost" onClick={() => navigate('/auth/login')}>
                                    Ir para o login
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </LoadingWrapper>
    )
}
