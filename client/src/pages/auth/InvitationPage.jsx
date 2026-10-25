import { useEffect, useState } from "react"
import { Link, useNavigate, useParams } from "react-router"

import { InputPassword } from "@/components/common/inputs/InputPassword"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { ThemeToggle } from "@/components/layout/ThemeToggle"
import { Button } from "@/components/ui/button"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { roleLabel } from "@/helpers/labels"
import { login, logout, me } from "@/lib/auth/auth"
import { acceptInvitation, getInvitation, registerInvitation } from "@/lib/auth/invitations"
import { notifyError, notifySuccess } from "@/lib/notify"

const INVALID = "Este link de convite não é válido. Peça um novo ao administrador."

function destinationFor(type) {
  return type === "fuel_station" ? "/posto" : "/app"
}

// "a Transportes X" ou "o Posto Exemplo".
function placeWithArticle(invitation) {
  return `${invitation.type === "fuel_station" ? "o" : "a"} ${invitation.name}`
}

/**
 * Lê o convite e, se houver sessão, quem está logado. Não mexe no estado da tela.
 */
async function readInvitation(token) {
  try {
    const invitation = await getInvitation(token)
    const user = localStorage.getItem("token") ? await me().catch(() => null) : null

    return { invitation, user, error: null }
  } catch (requestError) {
    return {
      invitation: null,
      user: null,
      error:
        requestError?.response?.status === 404
          ? INVALID
          : requestError?.response?.data?.message ?? "Não foi possível abrir o convite agora. Tente de novo em instantes.",
    }
  }
}

/**
 * Convite pelo link. Mostra um estado por vez: criar conta, entrar e aceitar,
 * aceitar logado, outra conta logada, já é membro, expirado ou inválido.
 */
export default function InvitationPage() {
  const { token } = useParams()
  const navigate = useNavigate()
  const [loading, setLoading] = useState(true)
  const [invitation, setInvitation] = useState(null)
  const [user, setUser] = useState(null)
  const [error, setError] = useState(null)
  const [name, setName] = useState("")
  const [password, setPassword] = useState("")
  const [passwordConfirmation, setPasswordConfirmation] = useState("")

  function applyState(state) {
    setInvitation(state.invitation)
    setUser(state.user)
    setError(state.error)
    setLoading(false)
  }

  // Quem chama já ligou o loading.
  function load() {
    return readInvitation(token).then(applyState)
  }

  useEffect(() => {
    readInvitation(token).then(applyState)
  }, [token])

  async function accept() {
    const result = await acceptInvitation(token)
    notifySuccess(result.already_member ? `Você já faz parte d${placeWithArticle(invitation)}` : "Convite aceito")
    navigate(destinationFor(result.type), { replace: true })
  }

  async function handleAccept() {
    setLoading(true)

    try {
      await accept()
    } catch (requestError) {
      notifyError(requestError)
      await load()
    }
  }

  async function handleLoginAndAccept(event) {
    event.preventDefault()
    setLoading(true)

    try {
      await login({ email: invitation.email, password })
    } catch (requestError) {
      notifyError(requestError)
      setLoading(false)
      return
    }

    try {
      await accept()
    } catch (requestError) {
      notifyError(requestError)
      await load()
    }
  }

  async function handleRegister(event) {
    event.preventDefault()
    setLoading(true)

    try {
      const result = await registerInvitation(token, {
        name,
        password,
        password_confirmation: passwordConfirmation,
      })
      notifySuccess("Conta criada")
      navigate(destinationFor(result.type), { replace: true })
    } catch (requestError) {
      notifyError(requestError)

      // O e-mail ganhou conta enquanto a tela estava aberta: mostra o "Entrar e aceitar".
      if (requestError?.response?.data?.errors?.email) {
        await load()
        return
      }

      setLoading(false)
    }
  }

  async function handleSwitchAccount() {
    setLoading(true)
    await logout().catch(() => null)
    setPassword("")
    await load()
  }

  return (
    <LoadingWrapper loading={loading}>
      <div className="grid min-h-svh lg:grid-cols-2">
        <div className="flex flex-col gap-4 p-6 md:p-10">
          <div className="flex items-center justify-between gap-2">
            <Link to="/auth/login" className="flex items-center gap-2 font-medium">
              <img src="/brand/logo-dark.svg" alt="Logiv" className="h-8 w-auto dark:hidden" />
              <img src="/brand/logo-mono.svg" alt="" className="hidden h-8 w-auto dark:block" />
            </Link>
            <ThemeToggle />
          </div>
          <div className="flex flex-1 items-center justify-center">
            <div className="w-full max-w-sm">
              {error ? (
                <Message title="Convite indisponível" text={error}>
                  <Button onClick={() => navigate("/auth/login")}>Ir para o login</Button>
                </Message>
              ) : null}

              {invitation?.expired ? (
                <Message
                  title="Convite expirado"
                  text={`Este convite expirou. Peça um novo link ao administrador d${placeWithArticle(invitation)}.`}
                />
              ) : null}

              {invitation && !invitation.expired && user ? (
                <LoggedState
                  invitation={invitation}
                  user={user}
                  onAccept={handleAccept}
                  onLater={() => navigate("/auth/login", { replace: true })}
                  onSwitchAccount={handleSwitchAccount}
                />
              ) : null}

              {invitation && !invitation.expired && !user && invitation.has_account ? (
                <form className="flex flex-col gap-6" onSubmit={handleLoginAndAccept}>
                  <FieldGroup>
                    <Heading
                      title="Aceitar convite"
                      text={`Entre com a sua conta para aceitar o convite d${placeWithArticle(invitation)}.`}
                    />
                    <Field>
                      <FieldLabel htmlFor="invitation-email">E-mail</FieldLabel>
                      <Input id="invitation-email" type="email" value={invitation.email} disabled readOnly />
                    </Field>
                    <Field>
                      <FieldLabel htmlFor="invitation-password">Senha</FieldLabel>
                      <InputPassword
                        id="invitation-password"
                        value={password}
                        onChange={setPassword}
                        autoComplete="current-password"
                        required
                      />
                    </Field>
                    <Field>
                      <Button type="submit" disabled={loading}>Entrar e aceitar</Button>
                    </Field>
                  </FieldGroup>
                </form>
              ) : null}

              {invitation && !invitation.expired && !user && !invitation.has_account ? (
                <form className="flex flex-col gap-6" onSubmit={handleRegister}>
                  <FieldGroup>
                    <Heading
                      title="Aceitar convite"
                      text={`Você foi convidado para ${placeWithArticle(invitation)} como ${roleLabel[invitation.role]}.`}
                    />
                    <Field>
                      <FieldLabel htmlFor="invitation-email">E-mail</FieldLabel>
                      <Input id="invitation-email" type="email" value={invitation.email} disabled readOnly />
                    </Field>
                    <Field>
                      <FieldLabel htmlFor="invitation-name">Nome</FieldLabel>
                      <Input
                        id="invitation-name"
                        value={name}
                        autoComplete="name"
                        onChange={(event) => setName(event.target.value)}
                        required
                      />
                    </Field>
                    <Field>
                      <FieldLabel htmlFor="invitation-password">Senha</FieldLabel>
                      <InputPassword
                        id="invitation-password"
                        value={password}
                        onChange={setPassword}
                        placeholder="Mínimo de 8 caracteres"
                        required
                      />
                    </Field>
                    <Field>
                      <FieldLabel htmlFor="invitation-password-confirmation">Confirmar senha</FieldLabel>
                      <InputPassword
                        id="invitation-password-confirmation"
                        value={passwordConfirmation}
                        onChange={setPasswordConfirmation}
                        placeholder="Repita a senha"
                        required
                      />
                    </Field>
                    <Field>
                      <Button type="submit" disabled={loading}>Criar conta e entrar</Button>
                    </Field>
                  </FieldGroup>
                </form>
              ) : null}
            </div>
          </div>
        </div>
        <div className="relative hidden bg-muted lg:block">
          <img
            src="/placeholder.svg"
            alt=""
            className="absolute inset-0 h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
          />
        </div>
      </div>
    </LoadingWrapper>
  )
}

function LoggedState({ invitation, user, onAccept, onLater, onSwitchAccount }) {
  if (user.email !== invitation.email) {
    return (
      <Message
        title="Outra conta"
        text={`Este convite é para ${invitation.email}, mas você entrou como ${user.email}.`}
      >
        <Button onClick={onSwitchAccount}>Sair e continuar como {invitation.email}</Button>
      </Message>
    )
  }

  if (invitation.is_member) {
    return (
      <Message title="Convite" text={`Você já faz parte d${placeWithArticle(invitation)}.`}>
        <Button onClick={onAccept}>Abrir {invitation.name}</Button>
      </Message>
    )
  }

  return (
    <Message
      title="Aceitar convite"
      text={`Você foi convidado para ${placeWithArticle(invitation)} como ${roleLabel[invitation.role]}.`}
    >
      <Button onClick={onAccept}>Aceitar convite</Button>
      <Button variant="outline" onClick={onLater}>Agora não</Button>
    </Message>
  )
}

function Heading({ title, text }) {
  return (
    <div className="flex flex-col items-center gap-1 text-center">
      <h1 className="text-2xl font-bold">{title}</h1>
      <p className="text-sm text-balance text-muted-foreground">{text}</p>
    </div>
  )
}

function Message({ title, text, children }) {
  return (
    <div className="flex flex-col gap-6">
      <Heading title={title} text={text} />
      {children ? <div className="flex flex-col gap-3">{children}</div> : null}
    </div>
  )
}
