import { useEffect, useState } from "react"
import { Link, useSearchParams } from "react-router"

import { InputCpfCnpj } from "@/components/common/inputs/InputCpfCnpj"
import { InputPassword } from "@/components/common/inputs/InputPassword"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { ThemeToggle } from "@/components/layout/ThemeToggle"
import { Button } from "@/components/ui/button"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { CodeStep } from "@/components/auth/CodeStep"
import { me } from "@/lib/auth/auth"
import { lookupStationCnpj, storeStationForUser, storeStationSignup } from "@/lib/auth/stationSignup"
import { sendVerificationCode, verificationError } from "@/lib/auth/verification"
import { notifyError, notifySuccess } from "@/lib/notify"

function onlyDigits(value) {
  return String(value ?? "").replace(/\D/g, "").slice(0, 14)
}

export default function RegisterFuelStationPage() {
  const [params] = useSearchParams()
  const [step, setStep] = useState(1)
  const [loading, setLoading] = useState(false)
  const [loggedIn, setLoggedIn] = useState(false)
  const [cnpj, setCnpj] = useState(() => onlyDigits(params.get("cnpj")))
  const [station, setStation] = useState(null)
  const [email, setEmail] = useState("")
  const [name, setName] = useState("")
  const [password, setPassword] = useState("")
  const [passwordConfirmation, setPasswordConfirmation] = useState("")
  const [retryAfter, setRetryAfter] = useState(60)
  const [codeError, setCodeError] = useState("")

  const cnpjReady = station?.document === cnpj

  useEffect(() => {
    if (!localStorage.getItem("token")) return

    let active = true
    setLoading(true)
    me()
      .then(() => {
        if (active) setLoggedIn(true)
      })
      .catch(() => {
        localStorage.removeItem("token")
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
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

  async function sendCode() {
    const response = await sendVerificationCode({
      email,
      purpose: "station_signup",
    })
    const seconds = Number(response.retry_after) || 60
    setRetryAfter(seconds)

    return response
  }

  async function finishSignup(code) {
    const result = await storeStationSignup({
      cnpj: station.document,
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
      code,
    })
    notifySuccess(result)
    window.location.assign("/posto")
  }

  async function handleAccountSubmit(event) {
    event.preventDefault()
    setLoading(true)
    setCodeError("")

    try {
      if (loggedIn) {
        const result = await storeStationForUser(station.document)
        notifySuccess(result)
        window.location.assign("/posto")
        return
      }

      if (password.length < 8) {
        setCodeError("A senha deve ter no mínimo 8 caracteres")
        return
      }

      if (password !== passwordConfirmation) {
        setCodeError("A confirmação da senha não confere")
        return
      }

      const response = await sendCode()

      if (response.sent === false) {
        setCodeError(response.message || "Aguarde para reenviar o código.")
        return
      }

      setStep(3)
    } catch (error) {
      notifyError(error)
    } finally {
      setLoading(false)
    }
  }

  async function handleCode(code) {
    setLoading(true)
    setCodeError("")

    try {
      await finishSignup(code)
    } catch (error) {
      setCodeError(verificationError(error, "Não foi possível confirmar o código."))
    } finally {
      setLoading(false)
    }
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
                        <p className="font-medium text-foreground">{station.legal_name}</p>
                        {station.trade_name ? <p>{station.trade_name}</p> : null}
                        <p>
                          {[station.street, station.street_number, station.complement, station.neighborhood, station.postal_code]
                            .filter(Boolean)
                            .join(", ")}
                        </p>
                        <p>{[station.city, station.state].filter(Boolean).join("/")}</p>
                      </div>
                    ) : null}
                    <Field>
                      <Button type="submit" disabled={loading || cnpj.length !== 14}>
                        {cnpjReady ? "Continuar" : "Consultar CNPJ"}
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

              {step === 3 ? (
                <div className="flex flex-col gap-6">
                  <div className="flex flex-col items-center gap-1 text-center">
                    <h1 className="text-2xl font-bold">Confirme o e-mail</h1>
                    <p className="text-sm text-balance text-muted-foreground">
                      O posto só é criado depois que o código for confirmado.
                    </p>
                  </div>
                  <CodeStep
                      email={email}
                      retryAfter={retryAfter}
                      submitting={loading}
                      error={codeError}
                      onSubmit={handleCode}
                      onResend={async () => {
                        setCodeError("")
                        try {
                          const response = await sendCode()

                          if (response.sent === false) {
                            setCodeError(response.message || "Aguarde para reenviar o código.")
                          }

                          return Number(response.retry_after) || 0
                        } catch (error) {
                          setCodeError(verificationError(error, "Não foi possível reenviar o código."))
                          return 0
                        }
                      }}
                      onChangeEmail={() => {
                        setStep(2)
                        setCodeError("")
                      }}
                    />
                </div>
              ) : null}

              {step === 2 ? (
                <form className="flex flex-col gap-6" onSubmit={handleAccountSubmit}>
                  <FieldGroup>
                    <div className="flex flex-col items-center gap-1 text-center">
                      <h1 className="text-2xl font-bold">
                        {loggedIn ? "Vincular posto à sua conta" : "Acesso do responsável"}
                      </h1>
                      <p className="text-sm text-balance text-muted-foreground">
                        {loggedIn
                          ? "O posto entra nesta conta. Você será o proprietário."
                          : "Quem cadastra o posto vira o proprietário."}
                      </p>
                    </div>
                    {loggedIn ? null : (
                      <>
                        <Field>
                          <FieldLabel htmlFor="station-name">Nome</FieldLabel>
                          <Input
                            id="station-name"
                            value={name}
                            autoComplete="name"
                            required
                            onChange={(event) => setName(event.target.value)}
                          />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="station-email">E-mail</FieldLabel>
                          <Input
                            id="station-email"
                            type="email"
                            placeholder="responsavel@posto.com.br"
                            value={email}
                            autoComplete="email"
                            required
                            onChange={(event) => setEmail(event.target.value)}
                          />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="station-password">Senha</FieldLabel>
                          <InputPassword
                            id="station-password"
                            value={password}
                            autoComplete="new-password"
                            onChange={setPassword}
                            placeholder="Mínimo de 8 caracteres"
                          />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="station-password-confirmation">Confirmação da senha</FieldLabel>
                          <InputPassword
                            id="station-password-confirmation"
                            value={passwordConfirmation}
                            autoComplete="new-password"
                            onChange={setPasswordConfirmation}
                            placeholder="Repita a senha"
                          />
                        </Field>
                      </>
                    )}
                    {codeError ? <p className="text-sm text-destructive" role="alert">{codeError}</p> : null}
                    <Field>
                      <Button type="submit" disabled={loading}>
                        {loggedIn ? "Cadastrar posto" : "Continuar"}
                      </Button>
                    </Field>
                    <Button type="button" variant="outline" onClick={() => setStep(1)}>
                      Voltar
                    </Button>
                    <p className="text-center text-sm text-muted-foreground">
                      <Link to="/auth/login" className="underline underline-offset-4 hover:text-foreground">
                        Voltar para o login
                      </Link>
                    </p>
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
