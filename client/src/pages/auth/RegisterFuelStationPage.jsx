import { useEffect, useState } from "react"
import { Link, useNavigate, useSearchParams } from "react-router"

import { InputCpfCnpj } from "@/components/common/inputs/InputCpfCnpj"
import { InputPassword } from "@/components/common/inputs/InputPassword"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { ThemeToggle } from "@/components/layout/ThemeToggle"
import { Button } from "@/components/ui/button"
import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { logout, me } from "@/lib/auth/auth"
import { lookupStationCnpj, storeStationForUser, storeStationSignup } from "@/lib/auth/stationSignup"
import { notifyError, notifySuccess } from "@/lib/notify"

/**
 * Quem está logado, ou null. Sessão vencida conta como deslogado.
 */
async function readUser() {
  if (!localStorage.getItem("token")) return null

  try {
    return await me()
  } catch {
    localStorage.removeItem("token")
    return null
  }
}

/**
 * Cadastro de posto em duas etapas: CNPJ e responsável.
 * Deslogado, cria uma conta nova. Logado, o posto vai pra conta logada.
 * Em ambos os casos a pessoa vira admin e entra direto em /posto.
 */
export default function RegisterFuelStationPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const [step, setStep] = useState(1)
  const [loading, setLoading] = useState(() => !!localStorage.getItem("token"))
  const [user, setUser] = useState(null)
  const [cnpj, setCnpj] = useState(() => (searchParams.get("cnpj") ?? "").replace(/\D/g, "").slice(0, 14))
  const [station, setStation] = useState(null)
  const [name, setName] = useState("")
  const [email, setEmail] = useState("")
  const [emailError, setEmailError] = useState(null)
  const [password, setPassword] = useState("")
  const [passwordConfirmation, setPasswordConfirmation] = useState("")

  const cnpjReady = station?.cnpj === cnpj
  // Na volta do login, o CNPJ já vem preenchido.
  const loginLink = `/auth/login?redirect=${encodeURIComponent(`/auth/register/posto?cnpj=${cnpj}`)}`

  useEffect(() => {
    readUser().then((current) => {
      setUser(current)
      setLoading(false)
    })
  }, [])

  async function handleCnpjSubmit(event) {
    event.preventDefault()

    if (cnpjReady) {
      setStep(2)
      return
    }

    setLoading(true)

    try {
      setStation(await lookupStationCnpj(cnpj))
    } catch (error) {
      setStation(null)
      notifyError(error)
    } finally {
      setLoading(false)
    }
  }

  async function handleAccountSubmit(event) {
    event.preventDefault()
    setEmailError(null)
    setLoading(true)

    try {
      const result = user
        ? await storeStationForUser(station.cnpj)
        : await storeStationSignup({
            cnpj: station.cnpj,
            name,
            email,
            password,
            password_confirmation: passwordConfirmation,
          })

      notifySuccess(result)
      navigate("/posto", { replace: true })
    } catch (error) {
      const fieldError = error?.response?.data?.errors?.email?.[0]
      if (fieldError) {
        setEmailError(fieldError)
      } else {
        notifyError(error)
      }
      setLoading(false)
    }
  }

  async function handleLogout() {
    setLoading(true)
    await logout().catch(() => {})
    setUser(null)
    setLoading(false)
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
            <div className="w-full max-w-md">
              {step === 1 ? (
                <form className="flex flex-col gap-6" onSubmit={handleCnpjSubmit}>
                  <FieldGroup>
                    <div className="flex flex-col items-center gap-1 text-center">
                      <h1 className="text-2xl font-bold">Cadastrar posto</h1>
                      <p className="text-sm text-balance text-muted-foreground">
                        Informe o CNPJ. Os dados da Receita Federal aparecem travados.
                      </p>
                    </div>
                    <Field>
                      <FieldLabel htmlFor="station-cnpj">CNPJ</FieldLabel>
                      <InputCpfCnpj
                        id="station-cnpj"
                        type="cnpj"
                        value={cnpj}
                        onChange={(value) => {
                          setCnpj(value)
                          setStation(null)
                        }}
                        autoFocus
                      />
                    </Field>
                    {cnpjReady ? (
                      <div className="space-y-1 text-sm text-muted-foreground">
                        <p className="font-medium text-foreground">{station.corporate_name}</p>
                        {station.trade_name ? <p>{station.trade_name}</p> : null}
                        <p>
                          {[station.street, station.number, station.complement, station.district, station.zip_code]
                            .filter(Boolean)
                            .join(", ")}
                        </p>
                        <p>{[station.city_name, station.uf].filter(Boolean).join("/")}</p>
                      </div>
                    ) : null}
                    <Field>
                      <Button type="submit" disabled={loading || cnpj.length !== 14}>
                        {cnpjReady ? "Continuar" : "Consultar"}
                      </Button>
                    </Field>
                    <p className="text-center text-sm text-muted-foreground">
                      <Link to="/auth/login" className="underline underline-offset-4 hover:text-foreground">
                        Voltar para o login
                      </Link>
                    </p>
                  </FieldGroup>
                </form>
              ) : null}

              {step === 2 ? (
                <form className="flex flex-col gap-6" onSubmit={handleAccountSubmit}>
                  <FieldGroup>
                    <div className="flex flex-col items-center gap-1 text-center">
                      <h1 className="text-2xl font-bold">Responsável pelo posto</h1>
                      <p className="text-sm text-balance text-muted-foreground">
                        {station.trade_name || station.corporate_name}
                      </p>
                    </div>

                    {user ? (
                      <p className="text-sm text-balance">
                        O posto será cadastrado na sua conta: <strong>{user.name}</strong> ({user.email}). Não é você?{" "}
                        <button
                          type="button"
                          className="underline underline-offset-4 hover:text-foreground"
                          onClick={handleLogout}
                        >
                          Sair
                        </button>
                        .
                      </p>
                    ) : (
                      <>
                        <p className="text-sm text-muted-foreground">Você será o administrador do posto.</p>
                        <Field>
                          <FieldLabel htmlFor="station-name">Nome</FieldLabel>
                          <Input
                            id="station-name"
                            value={name}
                            autoComplete="name"
                            onChange={(event) => setName(event.target.value)}
                            required
                          />
                        </Field>
                        <Field data-invalid={emailError ? true : undefined}>
                          <FieldLabel htmlFor="station-email">E-mail</FieldLabel>
                          <Input
                            id="station-email"
                            type="email"
                            placeholder="responsavel@posto.com.br"
                            value={email}
                            autoComplete="email"
                            aria-invalid={emailError ? true : undefined}
                            onChange={(event) => {
                              setEmail(event.target.value)
                              setEmailError(null)
                            }}
                            required
                          />
                          {emailError ? (
                            <FieldError>
                              {emailError}{" "}
                              <Link to={loginLink} className="font-medium underline underline-offset-4">
                                Entrar
                              </Link>
                            </FieldError>
                          ) : null}
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="station-password">Senha</FieldLabel>
                          <InputPassword
                            id="station-password"
                            value={password}
                            autoComplete="new-password"
                            onChange={setPassword}
                            placeholder="Mínimo de 8 caracteres"
                            required
                          />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="station-password-confirmation">Confirmar senha</FieldLabel>
                          <InputPassword
                            id="station-password-confirmation"
                            value={passwordConfirmation}
                            autoComplete="new-password"
                            onChange={setPasswordConfirmation}
                            placeholder="Repita a senha"
                            required
                          />
                        </Field>
                      </>
                    )}

                    <Field>
                      <Button type="submit" disabled={loading}>
                        Cadastrar posto
                      </Button>
                    </Field>
                    <Button type="button" variant="outline" onClick={() => setStep(1)}>
                      Voltar
                    </Button>
                    {user ? null : (
                      <p className="text-center text-sm text-muted-foreground">
                        <Link to={loginLink} className="underline underline-offset-4 hover:text-foreground">
                          Já tenho conta na Logiv
                        </Link>
                      </p>
                    )}
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
