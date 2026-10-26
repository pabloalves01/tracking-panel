import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'

import { InputPassword } from '@/components/common/inputs/InputPassword'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { me } from '@/lib/auth/auth'
import { acceptInvitation, declineInvitation, previewInvitation, registerInvitation } from '@/lib/auth/invitations'
import { invitationStatusLabel } from '@/helpers/labels/linkStatusLabel'
import { roleLabel } from '@/helpers/labels/roleLabel'
import { notifyError, notifySuccess } from '@/lib/notify'

export default function InvitationPage() {
    const { token } = useParams()
    const navigate = useNavigate()
    const [loading, setLoading] = useState(true)
    const [invite, setInvite] = useState(null)
    const [user, setUser] = useState(null)
    const [name, setName] = useState('')
    const [password, setPassword] = useState('')
    const [passwordConfirmation, setPasswordConfirmation] = useState('')

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
            } catch (error) {
                if (active) notifyError(error)
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

    async function handleRegister(event) {
        event.preventDefault()
        setLoading(true)

        try {
            const response = await registerInvitation(token, {
                name,
                password,
                password_confirmation: passwordConfirmation,
            })
            notifySuccess(response)
            goAfterAccept(invite?.organization?.type)
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    async function handleAccept() {
        setLoading(true)

        try {
            const response = await acceptInvitation(token)
            notifySuccess(response)
            goAfterAccept(invite?.organization?.type)
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    async function handleDecline() {
        setLoading(true)

        try {
            const response = await declineInvitation(token)
            notifySuccess(response)
            setInvite((current) => ({ ...current, status: 'declined' }))
        } catch (error) {
            notifyError(error)
        } finally {
            setLoading(false)
        }
    }

    const pending = invite?.status === 'pending'
    const sameEmail = user?.email && invite?.email && user.email.toLowerCase() === invite.email.toLowerCase()
    const loginRedirect = `/auth/login?redirect=${encodeURIComponent(`/auth/convite/${token}`)}`

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
                            <p className="text-center text-sm text-muted-foreground">Convite não encontrado.</p>
                        ) : (
                            <div className="flex flex-col gap-6">
                                <div className="flex flex-col items-center gap-1 text-center">
                                    <h1 className="text-2xl font-bold">Convite</h1>
                                    <p className="text-sm text-muted-foreground">
                                        {invite.organization?.name} convidou {invite.email} como {roleLabel[invite.role] ?? invite.role}.
                                    </p>
                                </div>

                                {pending && !invite.account_exists ? (
                                    <form className="flex flex-col gap-6" onSubmit={handleRegister}>
                                        <FieldGroup>
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
                                            <Button type="submit">Criar conta e aceitar</Button>
                                        </FieldGroup>
                                    </form>
                                ) : null}

                                {pending && invite.account_exists && !user ? (
                                    <Alert>
                                        <AlertTitle>Entre para aceitar</AlertTitle>
                                        <AlertDescription>
                                            Este e-mail já tem conta. Entre com ele para aceitar o convite.
                                        </AlertDescription>
                                        <Button className="mt-3" render={<Link to={loginRedirect} />}>
                                            Entrar
                                        </Button>
                                    </Alert>
                                ) : null}

                                {pending && user && sameEmail ? (
                                    <Button type="button" onClick={handleAccept}>Aceitar convite</Button>
                                ) : null}

                                {pending && user && !sameEmail ? (
                                    <Alert>
                                        <AlertTitle>Conta diferente</AlertTitle>
                                        <AlertDescription>
                                            Você entrou como {user.email}. Este convite é para {invite.email}.
                                        </AlertDescription>
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
