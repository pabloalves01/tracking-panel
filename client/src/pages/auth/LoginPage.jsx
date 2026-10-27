import { useEffect, useState } from "react"
import { Link, useNavigate, useSearchParams } from "react-router"

import { InputPassword } from "@/components/common/inputs/InputPassword"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { ThemeToggle } from "@/components/layout/ThemeToggle"
import { Button } from "@/components/ui/button"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { getAuthContexts, login, me } from "@/lib/auth/auth"
import { safeInternalPath } from "@/lib/auth/redirect"
import { apiMessage } from "@/lib/notify"

const REMEMBERED_EMAIL = "logiv.email"

function routeAfterLogin({ user, companies = [], fuel_stations: stations = [], invitations = [] }, redirect) {
  if (redirect) return redirect
  if (invitations.length > 0) return "/auth/contextos"

  const total = companies.length + stations.length

  if (total === 1) return stations.length === 1 ? "/posto" : "/app"
  if (total === 0 && user?.is_system_admin) return "/app"

  return "/auth/contextos"
}

export default function LoginPage() {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const redirect = safeInternalPath(params.get("redirect"))
  const emailLocked = params.get("email_locked") === "1"
  const [email, setEmail] = useState(() => params.get("email") || localStorage.getItem(REMEMBERED_EMAIL) || "")
  const [password, setPassword] = useState("")
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState("")
  const [secondsLeft, setSecondsLeft] = useState(0)
  const [checkingSession, setCheckingSession] = useState(() => !!localStorage.getItem("token"))

  useEffect(() => {
    if (secondsLeft <= 0) return undefined

    const timer = setTimeout(() => setSecondsLeft((current) => current - 1), 1000)

    return () => clearTimeout(timer)
  }, [secondsLeft])

  useEffect(() => {
    if (!localStorage.getItem("token")) return undefined

    let active = true

    Promise.all([me(), getAuthContexts()])
      .then(([user, contexts]) => {
        if (!active) return
        navigate(routeAfterLogin({
          user,
          companies: contexts.companies ?? [],
          fuel_stations: contexts.fuel_stations ?? [],
          invitations: contexts.invitations ?? [],
        }, redirect), { replace: true })
      })
      .catch(() => {
        localStorage.removeItem("token")
      })
      .finally(() => {
        if (active) setCheckingSession(false)
      })

    return () => {
      active = false
    }
  }, [navigate, redirect])

  function clearError() {
    if (secondsLeft > 0) return
    setError("")
  }

  async function handleSubmit(event) {
    event.preventDefault()

    if (secondsLeft > 0) return

    setLoading(true)
    setError("")

    try {
      const session = await login({ email, password })
      localStorage.setItem(REMEMBERED_EMAIL, email.trim())
      navigate(routeAfterLogin(session, redirect), { replace: true })
    } catch (requestError) {
      const retryAfter = Number(requestError?.response?.data?.retry_after || 0)

      if (requestError?.response?.status === 429 && retryAfter > 0) {
        setSecondsLeft(retryAfter)
        setError(`Muitas tentativas. Tente novamente em ${retryAfter} segundos.`)
      } else {
        setError(apiMessage(requestError, "Não foi possível entrar."))
      }
    } finally {
      setLoading(false)
    }
  }

  const visibleError = secondsLeft > 0
    ? `Muitas tentativas. Tente novamente em ${secondsLeft} segundos.`
    : error

  return (
    <LoadingWrapper loading={checkingSession || loading}>
    <div className="grid min-h-svh lg:grid-cols-2">
      <div className="flex flex-col gap-4 p-6 md:p-10">
        <div className="flex justify-between items-center gap-2">
          <a
            href="#"
            className="flex items-center gap-2 font-medium"
            onClick={(event) => event.preventDefault()}
          >
            <img src="/brand/logo-dark.svg" alt="Logiv" className="h-8 w-auto dark:hidden" />
            <img src="/brand/logo-mono.svg" alt="" className="hidden h-8 w-auto dark:block" />
          </a>
          <ThemeToggle />
        </div>
        <div className="flex flex-1 items-center justify-center">
          <div className="w-full max-w-xs">
            <form
              className="flex flex-col gap-6"
              onSubmit={handleSubmit}
            >
              <FieldGroup>
                <div className="flex flex-col items-center gap-1 text-center">
                  <h1 className="text-2xl font-bold">Entrar na sua conta</h1>
                  <p className="text-sm text-balance text-muted-foreground">
                    Informe seu e-mail para acessar o sistema
                  </p>
                </div>
                <Field>
                  <FieldLabel htmlFor="email">E-mail</FieldLabel>
                  <Input
                    id="email"
                    type="email"
                    placeholder="usuario@empresa.com.br"
                    value={email}
                    readOnly={emailLocked}
                    onChange={(event) => {
                      setEmail(event.target.value)
                      clearError()
                    }}
                    autoComplete="email"
                    required
                  />
                </Field>
                <Field>
                  <div className="flex items-center">
                    <FieldLabel htmlFor="password">Senha</FieldLabel>
                    <Link
                      to="/auth/forgot-password"
                      className="ml-auto text-sm underline-offset-4 hover:underline"
                    >
                      Esqueceu a senha?
                    </Link>
                  </div>
                  <InputPassword
                    id="password"
                    placeholder="**********"
                    value={password}
                    onChange={(value) => {
                      setPassword(value)
                      clearError()
                    }}
                    autoComplete="current-password"
                  />
                </Field>
                {visibleError ? (
                  <p className="text-sm text-destructive" role="alert">{visibleError}</p>
                ) : null}
                <Field>
                  <Button type="submit" disabled={loading || secondsLeft > 0}>
                    {loading ? "Entrando..." : "Entrar"}
                  </Button>
                </Field>
                <p className="text-center text-sm text-muted-foreground">
                  <Link
                    to="/auth/register/posto"
                    className="underline underline-offset-4 hover:text-foreground"
                  >
                    Sou posto, quero me cadastrar
                  </Link>
                </p>
              </FieldGroup>
            </form>
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
