import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'

import { CodeStep } from '@/components/auth/CodeStep'
import { InputPassword } from '@/components/common/inputs/InputPassword'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { Button } from '@/components/ui/button'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { safeInternalPath } from '@/lib/auth/redirect'
import { forgotPassword, resetPassword, verificationError } from '@/lib/auth/verification'

export default function ForgotPasswordPage() {
    const navigate = useNavigate()
    const [params] = useSearchParams()
    // Quem chega de um cadastro de posto ou de um convite volta para lá
    // depois de trocar a senha, em vez de perder o caminho no login.
    const back = safeInternalPath(params.get('back'))
    const [step, setStep] = useState('email')
    const [email, setEmail] = useState(() => params.get('email') || localStorage.getItem('logiv.email') || '')
    const [password, setPassword] = useState('')
    const [passwordConfirmation, setPasswordConfirmation] = useState('')
    const [retryAfter, setRetryAfter] = useState(60)
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState('')

    async function sendCode() {
        setLoading(true)
        setError('')

        try {
            const response = await forgotPassword(email)
            const seconds = Number(response.retry_after) || 60
            setRetryAfter(seconds)

            if (response.sent === false) {
                setError(response.message || 'Aguarde para reenviar o código.')
                return seconds
            }

            setStep('code')

            return seconds
        } catch (requestError) {
            setError(verificationError(requestError, 'Não foi possível enviar o código.'))
        } finally {
            setLoading(false)
        }
    }

    async function handleEmail(event) {
        event.preventDefault()
        await sendCode()
    }

    async function handleReset(code) {
        if (password.length < 8) {
            setError('A senha deve ter no mínimo 8 caracteres')
            return
        }

        if (password !== passwordConfirmation) {
            setError('A confirmação da senha não confere')
            return
        }

        setLoading(true)
        setError('')

        try {
            await resetPassword({
                email,
                code,
                password,
                password_confirmation: passwordConfirmation,
            })
            navigate(back ?? '/auth/login', { replace: true })
        } catch (requestError) {
            setError(verificationError(requestError, 'Não foi possível atualizar a senha.'))
        } finally {
            setLoading(false)
        }
    }

    return (
        <LoadingWrapper loading={loading && step === 'email'}>
            <div className="flex min-h-svh flex-col gap-4 p-6 md:p-10">
                <div className="flex items-center justify-between">
                    <Link to="/auth/login" className="flex items-center gap-2 font-medium">
                        <img src="/brand/logo-dark.svg" alt="Logiv" className="h-8 w-auto dark:hidden" />
                        <img src="/brand/logo-mono.svg" alt="" className="hidden h-8 w-auto dark:block" />
                    </Link>
                    <ThemeToggle />
                </div>
                <div className="flex flex-1 items-center justify-center">
                    <div className="w-full max-w-sm">
                        {step === 'email' ? (
                            <form className="flex flex-col gap-6" onSubmit={handleEmail}>
                                <FieldGroup>
                                    <div className="flex flex-col items-center gap-1 text-center">
                                        <h1 className="text-2xl font-bold">Esqueceu a senha?</h1>
                                        <p className="text-sm text-muted-foreground">
                                            Enviaremos um código de 6 dígitos para o e-mail da conta.
                                        </p>
                                    </div>
                                    <Field>
                                        <FieldLabel htmlFor="reset-email">E-mail</FieldLabel>
                                        <Input
                                            id="reset-email"
                                            type="email"
                                            required
                                            autoComplete="email"
                                            value={email}
                                            onChange={(event) => {
                                                setEmail(event.target.value)
                                                setError('')
                                            }}
                                        />
                                    </Field>
                                    {error ? <p className="text-sm text-destructive" role="alert">{error}</p> : null}
                                    <Button type="submit" disabled={loading}>Enviar código</Button>
                                </FieldGroup>
                            </form>
                        ) : (
                            <div className="flex flex-col gap-6">
                                <div className="flex flex-col items-center gap-1 text-center">
                                    <h1 className="text-2xl font-bold">Nova senha</h1>
                                    <p className="text-sm text-muted-foreground">
                                        Digite o código e escolha a nova senha.
                                    </p>
                                </div>
                                <Field>
                                    <FieldLabel htmlFor="reset-password">Nova senha</FieldLabel>
                                    <InputPassword
                                        id="reset-password"
                                        value={password}
                                        autoComplete="new-password"
                                        placeholder="Mínimo de 8 caracteres"
                                        onChange={(value) => {
                                            setPassword(value)
                                            setError('')
                                        }}
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="reset-password-confirmation">Confirmação da senha</FieldLabel>
                                    <InputPassword
                                        id="reset-password-confirmation"
                                        value={passwordConfirmation}
                                        autoComplete="new-password"
                                        placeholder="Repita a senha"
                                        onChange={(value) => {
                                            setPasswordConfirmation(value)
                                            setError('')
                                        }}
                                    />
                                </Field>
                                <CodeStep
                                    email={email}
                                    retryAfter={retryAfter}
                                    submitting={loading}
                                    error={error}
                                    onSubmit={handleReset}
                                    onResend={sendCode}
                                    onChangeEmail={() => {
                                        setStep('email')
                                        setError('')
                                    }}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </LoadingWrapper>
    )
}
