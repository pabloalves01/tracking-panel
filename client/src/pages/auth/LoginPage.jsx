import { useEffect, useState } from "react"
import { Link, useNavigate, useSearchParams } from "react-router"

import { ThemeToggle } from "@/components/layout/ThemeToggle"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { Button } from "@/components/ui/button"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { login, me, safeRedirect } from "@/lib/auth/auth"
import { notifyError } from "@/lib/notify"

// Sessão que já existe: vai pro contexto do token. Admin da Logiv sem contexto vai pro sistema.
function destinationFor(user) {
  if (user.has_company) return "/app"
  if (user.has_fuel_station) return "/posto"
  if (user.is_system_admin) return "/app/sistema"
  return "/auth/contextos"
}

export default function LoginPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const redirect = safeRedirect(searchParams.get("redirect"))
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [loading, setLoading] = useState(false)
  const [checkingSession, setCheckingSession] = useState(() => !!localStorage.getItem("token"))

  useEffect(() => {
    if (!localStorage.getItem("token")) return

    let active = true

    me()
      .then((user) => {
        if (!active) return
        navigate(redirect ?? destinationFor(user), { replace: true })
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

  async function handleSubmit(event) {
    event.preventDefault()
    setLoading(true)

    try {
      const { user, companies, fuel_stations: stations = [] } = await login({ email, password })
      const total = companies.length + stations.length

      if (redirect) {
        navigate(redirect, { replace: true })
        return
      }

      if (total === 1) {
        navigate(stations.length === 1 ? "/posto" : "/app", { replace: true })
        return
      }

      // Admin da Logiv sem vínculo vai pro sistema. Os demais escolhem
      // (ou veem o estado vazio) em "Onde você quer entrar?".
      navigate(total === 0 && user.is_system_admin ? "/app/sistema" : "/auth/contextos", { replace: true })
    } catch (error) {
      notifyError(error)
    } finally {
      setLoading(false)
    }
  }

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
            {/* Azul com laranja no light, só laranja no dark. */}
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
                  <h1 className="text-2xl font-bold">Entrar na Logiv</h1>
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
                    onChange={(event) => setEmail(event.target.value)}
                    autoComplete="email"
                    required
                  />
                </Field>
                <Field>
                  <FieldLabel htmlFor="password">Senha</FieldLabel>
                  <Input
                    id="password"
                    type="password"
                    placeholder="**********"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    autoComplete="current-password"
                    required
                  />
                </Field>
                <Field>
                  <Button type="submit" disabled={loading}>
                    {loading ? "Entrando..." : "Entrar"}
                  </Button>
                </Field>
                <div className="flex flex-col gap-2 text-center text-sm text-muted-foreground">
                  <Link
                    to="/auth/register/posto"
                    className="underline underline-offset-4 hover:text-foreground"
                  >
                    Quero cadastrar meu posto
                  </Link>
                  <p>Recebeu um convite? Abra o link que enviaram pra você.</p>
                </div>
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
