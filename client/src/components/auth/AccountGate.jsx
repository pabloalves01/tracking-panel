import { useState } from 'react'
import { Link } from 'react-router'

import { CodeStep } from '@/components/auth/CodeStep'
import { InputPassword } from '@/components/common/inputs/InputPassword'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { checkAccountCode, isAccountExistsConflict, registerAccount, sendAccountCode } from '@/lib/auth/account'
import { login } from '@/lib/auth/auth'
import { verificationError } from '@/lib/auth/verification'
import { apiMessage } from '@/lib/notify'

const EXISTING_NOTICE = 'Este e-mail já tem uma conta no LogIV. Entre com a senha que você já usa — não é preciso criar outra.'

/**
 * "Quem é você?" — o passo que termina numa sessão, para quem ainda não tem.
 *
 *   e-mail → código → conferir
 *       ├─ conta existe → senha atual
 *       └─ conta nova   → nome e senha
 *
 * É o único lugar do client que decide entre entrar e criar conta, e ele só
 * decide **depois** de saber: o formulário de senha nova só aparece para
 * quem o servidor confirmou que não tem conta. Antes, o cadastro de posto e o
 * convite tinham cada um o seu fluxo, e os dois pediam nome e senha antes de
 * saber quem era a pessoa.
 *
 * Com `email` a caixa vem travada (o convite já diz para quem é). Com
 * `accountExists === true` — o convite também sabe disso — vai direto para a
 * senha, sem código.
 *
 * `onAuthenticated(session)` recebe a resposta do login ou do cadastro e
 * cuida dos próprios erros: daqui em diante a pessoa já está logada, e uma
 * falha ao criar o posto não é uma falha de identidade.
 */
export function AccountGate({
    email: lockedEmail,
    accountExists,
    title = 'Quem vai administrar?',
    description = 'Informe seu e-mail. Enviaremos um código para confirmar.',
    forgotBack,
    onAuthenticated,
}) {
    const locked = Boolean(lockedEmail)
    const [step, setStep] = useState(locked && accountExists === true ? 'password' : 'email')
    const [email, setEmail] = useState(lockedEmail ?? '')
    const [code, setCode] = useState('')
    const [name, setName] = useState('')
    const [password, setPassword] = useState('')
    const [passwordConfirmation, setPasswordConfirmation] = useState('')
    const [currentPassword, setCurrentPassword] = useState('')
    const [retryAfter, setRetryAfter] = useState(60)
    const [notice, setNotice] = useState('')
    const [error, setError] = useState('')
    const [busy, setBusy] = useState(false)

    function goTo(next, { message = '', info = '' } = {}) {
        setError(message)
        setNotice(info)
        setStep(next)
    }

    /**
     * Pedir de novo dentro do intervalo não é erro: o código anterior segue
     * valendo, então a tela vai para o código do mesmo jeito e só avisa.
     */
    async function requestCode() {
        const response = await sendAccountCode(email)
        const seconds = Number(response.retry_after) || 60
        setRetryAfter(seconds)

        return { seconds, message: response.sent === false ? response.message : '' }
    }

    async function handleEmail(event) {
        event.preventDefault()
        setBusy(true)
        setError('')

        try {
            const { message } = await requestCode()
            goTo('code', { message })
        } catch (requestError) {
            setError(verificationError(requestError, 'Não foi possível enviar o código.'))
        } finally {
            setBusy(false)
        }
    }

    async function handleCode(typed) {
        setBusy(true)
        setError('')

        try {
            const response = await checkAccountCode(email, typed)
            setCode(typed)

            if (response.account_exists) {
                goTo('password', { info: EXISTING_NOTICE })
            } else {
                goTo('details')
            }
        } catch (requestError) {
            setError(verificationError(requestError, 'Não foi possível confirmar o código.'))
        } finally {
            setBusy(false)
        }
    }

    async function handleDetails(event) {
        event.preventDefault()

        if (password.length < 8) {
            setError('A senha deve ter no mínimo 8 caracteres')
            return
        }

        if (password !== passwordConfirmation) {
            setError('A confirmação da senha não confere')
            return
        }

        setBusy(true)
        setError('')

        let session

        try {
            session = await registerAccount({
                email,
                code,
                name,
                password,
                password_confirmation: passwordConfirmation,
            })
        } catch (requestError) {
            setBusy(false)

            // A conta passou a existir enquanto o formulário estava aberto.
            // Nada foi gravado: a pessoa entra com a senha que já tem.
            if (isAccountExistsConflict(requestError)) {
                setPassword('')
                setPasswordConfirmation('')
                goTo('password', { info: EXISTING_NOTICE })
                return
            }

            // O código venceu enquanto a pessoa digitava o nome e a senha.
            if (requestError?.response?.status === 422 && requestError?.response?.data?.errors === undefined) {
                goTo('code', { message: 'O código expirou. Peça um novo para continuar.' })
                return
            }

            setError(apiMessage(requestError, 'Não foi possível criar a conta.'))
            return
        }

        await onAuthenticated(session)
        setBusy(false)
    }

    async function handlePassword(event) {
        event.preventDefault()
        setBusy(true)
        setError('')

        let session

        try {
            session = await login({ email, password: currentPassword })
        } catch (requestError) {
            setBusy(false)
            setError(apiMessage(requestError, 'Não foi possível entrar.'))
            return
        }

        localStorage.setItem('logiv.email', email.trim())
        await onAuthenticated(session)
        setBusy(false)
    }

    const forgotLink = `/auth/forgot-password?email=${encodeURIComponent(email)}${
        forgotBack ? `&back=${encodeURIComponent(forgotBack)}` : ''
    }`

    const errorLine = error ? (
        <p className="text-sm text-destructive" role="alert">{error}</p>
    ) : null

    if (step === 'code') {
        return (
            <div className="flex flex-col gap-6">
                <Heading title="Confirme seu e-mail" description="Digite o código de 6 dígitos que enviamos." />
                <CodeStep
                    email={email}
                    retryAfter={retryAfter}
                    submitting={busy}
                    error={error}
                    onSubmit={handleCode}
                    onResend={async () => {
                        setError('')
                        try {
                            const { seconds, message } = await requestCode()
                            setError(message)
                            return seconds
                        } catch (requestError) {
                            setError(verificationError(requestError, 'Não foi possível reenviar o código.'))
                            return 0
                        }
                    }}
                    onChangeEmail={locked ? undefined : () => goTo('email')}
                />
            </div>
        )
    }

    if (step === 'details') {
        return (
            <form className="flex flex-col gap-6" onSubmit={handleDetails}>
                <FieldGroup>
                    <Heading
                        title="Crie seu acesso"
                        description="E-mail confirmado. Defina seu nome e uma senha para os próximos acessos."
                    />
                    <Field>
                        <FieldLabel htmlFor="account-email">E-mail</FieldLabel>
                        <Input id="account-email" type="email" value={email} readOnly disabled />
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="account-name">Nome</FieldLabel>
                        <Input
                            id="account-name"
                            value={name}
                            autoComplete="name"
                            placeholder="Digite seu nome"
                            required
                            autoFocus
                            onChange={(event) => setName(event.target.value)}
                        />
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="account-password">Senha</FieldLabel>
                        <InputPassword
                            id="account-password"
                            value={password}
                            autoComplete="new-password"
                            placeholder="Mínimo de 8 caracteres"
                            onChange={setPassword}
                        />
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="account-password-confirmation">Confirmação da senha</FieldLabel>
                        <InputPassword
                            id="account-password-confirmation"
                            value={passwordConfirmation}
                            autoComplete="new-password"
                            placeholder="Repita a senha"
                            onChange={setPasswordConfirmation}
                        />
                    </Field>
                    {errorLine}
                    <Button type="submit" disabled={busy}>
                        {busy ? 'Criando conta...' : 'Criar conta e continuar'}
                    </Button>
                </FieldGroup>
            </form>
        )
    }

    if (step === 'password') {
        return (
            <form className="flex flex-col gap-6" onSubmit={handlePassword}>
                <FieldGroup>
                    {/* Quando a tela chegou aqui por descoberta — o código
                        revelou que a conta existe —, o aviso é o subtítulo. */}
                    <Heading title="Entre na sua conta" description={notice || 'Use a senha que você já tem.'} />
                    <Field>
                        <FieldLabel htmlFor="account-login-email">E-mail</FieldLabel>
                        <Input id="account-login-email" type="email" value={email} readOnly disabled />
                    </Field>
                    <Field>
                        <div className="flex items-center">
                            <FieldLabel htmlFor="account-current-password">Senha</FieldLabel>
                            <Link to={forgotLink} className="ml-auto text-sm underline-offset-4 hover:underline">
                                Esqueceu a senha?
                            </Link>
                        </div>
                        <InputPassword
                            id="account-current-password"
                            value={currentPassword}
                            autoComplete="current-password"
                            onChange={setCurrentPassword}
                        />
                    </Field>
                    {errorLine}
                    <Button type="submit" disabled={busy || currentPassword === ''}>
                        {busy ? 'Entrando...' : 'Entrar e continuar'}
                    </Button>
                    {locked ? null : (
                        <button
                            type="button"
                            className="text-center text-sm text-muted-foreground underline-offset-4 hover:underline"
                            onClick={() => {
                                setCurrentPassword('')
                                goTo('email')
                            }}
                        >
                            Usar outro e-mail
                        </button>
                    )}
                </FieldGroup>
            </form>
        )
    }

    return (
        <form className="flex flex-col gap-6" onSubmit={handleEmail}>
            <FieldGroup>
                <Heading title={title} description={description} />
                <Field>
                    <FieldLabel htmlFor="account-email-start">E-mail</FieldLabel>
                    <Input
                        id="account-email-start"
                        type="email"
                        placeholder="voce@empresa.com.br"
                        value={email}
                        readOnly={locked}
                        disabled={locked}
                        autoComplete="email"
                        required
                        autoFocus={!locked}
                        onChange={(event) => setEmail(event.target.value)}
                    />
                    {locked ? null : (
                        <FieldDescription>
                            Se você já usa o LogIV, use o mesmo e-mail: o acesso fica na mesma conta.
                        </FieldDescription>
                    )}
                </Field>
                {errorLine}
                <Button type="submit" disabled={busy}>
                    {busy ? 'Enviando...' : 'Enviar código'}
                </Button>
            </FieldGroup>
        </form>
    )
}

function Heading({ title, description }) {
    return (
        <div className="flex flex-col items-center gap-1 text-center">
            <h1 className="text-2xl font-bold">{title}</h1>
            {description ? <p className="text-sm text-balance text-muted-foreground">{description}</p> : null}
        </div>
    )
}
