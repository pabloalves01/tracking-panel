import { useState } from "react"
import { Link, useNavigate } from "react-router"

import { InputCpfCnpj } from "@/components/common/inputs/InputCpfCnpj"
import { InputPassword } from "@/components/common/inputs/InputPassword"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { ThemeToggle } from "@/components/layout/ThemeToggle"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { Button } from "@/components/ui/button"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { checkStationSignupEmail, lookupStationCnpj, storeStationSignup } from "@/lib/auth/stationSignup"
import { notifyError, notifySuccess } from "@/lib/notify"

export default function RegisterFuelStationPage() {
  const navigate = useNavigate()
  const [step, setStep] = useState(1)
  const [loading, setLoading] = useState(false)
  const [cnpj, setCnpj] = useState("")
  const [station, setStation] = useState(null)
  const [email, setEmail] = useState("")
  const [emailExists, setEmailExists] = useState(null)
  const [name, setName] = useState("")
  const [password, setPassword] = useState("")
  const [passwordConfirmation, setPasswordConfirmation] = useState("")

  const cnpjReady = station?.cnpj === cnpj

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
    setLoading(true)

    try {
      if (emailExists === null) {
        const result = await checkStationSignupEmail({ email })
        setEmailExists(Boolean(result.exists))
        return
      }

      const result = await storeStationSignup(
        emailExists
          ? {
              cnpj: station.cnpj,
              email,
              password,
            }
          : {
              cnpj: station.cnpj,
              name,
              email,
              password,
              password_confirmation: passwordConfirmation,
            },
      )

      notifySuccess(result)
      navigate("/auth/login", { replace: true })
    } catch (error) {
      notifyError(error)
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
                        <p className="font-medium text-foreground">{station.corporate_name}</p>
                        {station.trade_name ? <p>{station.trade_name}</p> : null}
                        <p>{station.receita_status}</p>
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

              {step === 2 ? (
                <form className="flex flex-col gap-6" onSubmit={handleAccountSubmit}>
                  <FieldGroup>
                    <div className="flex flex-col items-center gap-1 text-center">
                      <h1 className="text-2xl font-bold">Acesso do responsável</h1>
                      {emailExists ? null : (
                        <p className="text-sm text-balance text-muted-foreground">
                          Informe o e-mail de quem vai administrar o posto.
                        </p>
                      )}
                    </div>
                    <Field>
                      <FieldLabel htmlFor="station-email">E-mail</FieldLabel>
                      <Input
                        id="station-email"
                        type="email"
                        placeholder="responsavel@posto.com.br"
                        value={email}
                        autoComplete="email"
                        onChange={(event) => {
                          setEmail(event.target.value)
                          setEmailExists(null)
                        }}
                      />
                    </Field>
                    {emailExists === false ? (
                      <>
                        <Field>
                          <FieldLabel htmlFor="station-name">Nome</FieldLabel>
                          <Input
                            id="station-name"
                            value={name}
                            autoComplete="name"
                            onChange={(event) => setName(event.target.value)}
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
                    ) : null}
                    {emailExists === true ? (
                      <>
                        <Alert>
                          <AlertTitle>Conta já existente</AlertTitle>
                          <AlertDescription>
                            Este e-mail já tem uma conta na Logiv. Digite a senha dessa conta para vincular o posto a ela.
                          </AlertDescription>
                        </Alert>
                        <Field>
                          <FieldLabel htmlFor="station-current-password">Senha atual</FieldLabel>
                          <InputPassword
                            id="station-current-password"
                            value={password}
                            autoComplete="current-password"
                            onChange={setPassword}
                            placeholder="Senha da conta"
                          />
                        </Field>
                      </>
                    ) : null}
                    <Field>
                      <Button type="submit" disabled={loading}>
                        {emailExists === null
                          ? "Continuar"
                          : emailExists
                            ? "Vincular posto à minha conta"
                            : "Cadastrar posto"}
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
