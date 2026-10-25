import { useEffect, useState } from "react"
import { Link, useNavigate } from "react-router"

import { ThemeToggle } from "@/components/layout/ThemeToggle"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { Button } from "@/components/ui/button"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { login, me } from "@/lib/auth/auth"
import { notifyError } from "@/lib/notify"

// Empresa, depois posto, depois admin sem contexto. Admin com posto entra no posto.
function destinationFor(user) {
  if (user.has_company) return "/app"
  if (user.has_fuel_station) return "/posto"
  if (user.is_system_admin) return "/app"
  return "/auth/contextos"
}

export default function LoginPage() {
  const navigate = useNavigate()
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
        navigate(destinationFor(user), { replace: true })
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
  }, [navigate])

  async function handleSubmit(event) {
    event.preventDefault()
    setLoading(true)

    try {
      const { user, companies, fuel_stations: stations = [] } = await login({ email, password })
      const total = companies.length + stations.length

      if (total === 1) {
        navigate(stations.length === 1 ? "/posto" : "/app", { replace: true })
        return
      }

      // Admin do sistema não precisa de empresa; segue direto para o painel.
      if (total === 0 && user.is_system_admin) {
        navigate("/app", { replace: true })
        return
      }

      // Sem nenhum contexto, a tela de contextos explica o que fazer.
      navigate("/auth/contextos", { replace: true })
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
                    onChange={(event) => setEmail(event.target.value)}
                    autoComplete="email"
                    required
                  />
                </Field>
                <Field>
                  <div className="flex items-center">
                    <FieldLabel htmlFor="password">Senha</FieldLabel>
                    <a
                      href="#"
                      className="ml-auto text-sm underline-offset-4 hover:underline"
                      onClick={(event) => event.preventDefault()}
                    >
                      Esqueceu a senha?
                    </a>
                  </div>
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
                <p className="text-center text-sm text-muted-foreground">
                  Não tem conta?{" "}
                  <Link
                    to="/auth/register"
                    className="underline underline-offset-4 hover:text-foreground"
                  >
                    Criar conta
                  </Link>
                </p>
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
