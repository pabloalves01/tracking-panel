import { useState } from "react"
import { Link, useNavigate } from "react-router"

import { InputCity } from "@/components/common/inputs/InputCity"
import { InputCpfCnpj } from "@/components/common/inputs/InputCpfCnpj"
import { InputPassword } from "@/components/common/inputs/InputPassword"
import { InputPostalCode } from "@/components/common/inputs/InputPostalCode"
import { SelectState } from "@/components/common/inputs/SelectState"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { ThemeToggle } from "@/components/layout/ThemeToggle"
import { Button } from "@/components/ui/button"
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import {
  checkStationSignupEmail,
  lookupStationCnpj,
  resendStationSignupCode,
  startStationSignup,
  verifyStationSignup,
} from "@/lib/auth/stationSignup"
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
  const [signupUuid, setSignupUuid] = useState("")
  const [maskedEmail, setMaskedEmail] = useState("")
  const [code, setCode] = useState("")

  const cnpjReady = station?.cnpj === cnpj

  async function handleCnpjSubmit(event) {
    event.preventDefault()

    if (cnpjReady) {
      setStep(2)
      return
    }

    if (cnpj.length !== 14) {
      notifyError({ response: { status: 422, data: { message: "Informe um CNPJ válido" } } })
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
    const normalizedEmail = email.trim().toLowerCase()

    if (!normalizedEmail) {
      notifyError({ response: { status: 422, data: { message: "O e-mail é obrigatório" } } })
      return
    }

    setLoading(true)

    try {
      if (emailExists === null) {
        const result = await checkStationSignupEmail({
          cnpj: station.cnpj,
          email: normalizedEmail,
        })
        setEmail(normalizedEmail)
        setEmailExists(Boolean(result.exists))
        return
      }

      if (!emailExists && (!name.trim() || password.length < 8)) {
        notifyError({
          response: {
            status: 422,
            data: {
              message: !name.trim() ? "O nome é obrigatório" : "A senha deve ter no mínimo 8 caracteres",
            },
          },
        })
        return
      }

      if (!emailExists && password !== passwordConfirmation) {
        notifyError({ response: { status: 422, data: { message: "A confirmação da senha não confere" } } })
        return
      }

      if (emailExists && !password) {
        notifyError({ response: { status: 422, data: { message: "A senha é obrigatória" } } })
        return
      }

      const result = await startStationSignup(
        emailExists
          ? {
              cnpj: station.cnpj,
              email: normalizedEmail,
              password,
            }
          : {
              cnpj: station.cnpj,
              name: name.trim(),
              email: normalizedEmail,
              password,
              password_confirmation: passwordConfirmation,
            },
      )

      if (result.signup_uuid) {
        setSignupUuid(result.signup_uuid)
        setMaskedEmail(result.email_masked ?? normalizedEmail)
        setStep(3)
        return
      }

      notifySuccess(result)
      navigate("/auth/login", { replace: true })
    } catch (error) {
      notifyError(error)
    } finally {
      setLoading(false)
    }
  }

  async function handleVerify(event) {
    event.preventDefault()

    if (code.length !== 6) {
      notifyError({ response: { status: 422, data: { message: "O código deve ter 6 dígitos" } } })
      return
    }

    setLoading(true)

    try {
      const result = await verifyStationSignup(signupUuid, code)
      notifySuccess(result)
      navigate("/auth/login", { replace: true })
    } catch (error) {
      notifyError(error)
    } finally {
      setLoading(false)
    }
  }

  async function handleResend() {
    setLoading(true)

    try {
      await resendStationSignupCode(signupUuid)
      setCode("")
      notifySuccess({ message: "Enviamos um novo código." })
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
                      <>
                        <Field>
                          <FieldLabel htmlFor="station-corporate-name">Razão social</FieldLabel>
                          <Input id="station-corporate-name" value={station.corporate_name ?? ""} disabled />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="station-trade-name">Nome fantasia</FieldLabel>
                          <Input id="station-trade-name" value={station.trade_name ?? ""} disabled />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="station-receita-status">Situação cadastral</FieldLabel>
                          <Input id="station-receita-status" value={station.receita_status ?? ""} disabled />
                        </Field>
                        <FieldDescription>Endereço informado pela Receita Federal.</FieldDescription>
                        <Field>
                          <FieldLabel htmlFor="station-zip-code">CEP</FieldLabel>
                          <InputPostalCode id="station-zip-code" value={station.zip_code ?? ""} disabled />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="station-street">Rua</FieldLabel>
                          <Input id="station-street" value={station.street ?? ""} disabled />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="station-number">Número</FieldLabel>
                          <Input id="station-number" value={station.number ?? ""} disabled />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="station-complement">Complemento</FieldLabel>
                          <Input id="station-complement" value={station.complement ?? ""} disabled />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="station-district">Bairro</FieldLabel>
                          <Input id="station-district" value={station.district ?? ""} disabled />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="station-state">Estado</FieldLabel>
                          {station.state_id ? (
                            <SelectState id="station-state" value={station.state_id} disabled onChange={() => {}} />
                          ) : (
                            <Input id="station-state" value={station.state_name || station.uf || ""} disabled />
                          )}
                        </Field>
                        <Field data-disabled>
                          <FieldLabel htmlFor="station-city">Cidade</FieldLabel>
                          {station.state_id ? (
                            <InputCity
                              id="station-city"
                              stateId={station.state_id}
                              value={station.city_id}
                              disabled
                              onChange={() => {}}
                            />
                          ) : (
                            <Input id="station-city" value={station.city_name ?? ""} disabled />
                          )}
                        </Field>
                      </>
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
                      <p className="text-sm text-balance text-muted-foreground">
                        {emailExists
                          ? "Este e-mail já tem conta. Informe a senha atual para vincular o posto."
                          : "Informe o e-mail de quem vai administrar o posto."}
                      </p>
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
                        required
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
                            required
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
                    ) : null}
                    <Field>
                      <Button type="submit" disabled={loading}>
                        {emailExists === null
                          ? "Continuar"
                          : emailExists
                            ? "Cadastrar posto"
                            : "Enviar código"}
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

              {step === 3 ? (
                <form className="flex flex-col gap-6" onSubmit={handleVerify}>
                  <FieldGroup>
                    <div className="flex flex-col items-center gap-1 text-center">
                      <h1 className="text-2xl font-bold">Confirme o código</h1>
                      <p className="text-sm text-balance text-muted-foreground">
                        {`Enviamos um código de 6 dígitos para ${maskedEmail}. Ele expira em 10 minutos.`}
                      </p>
                    </div>
                    <Field>
                      <FieldLabel htmlFor="station-code">Código</FieldLabel>
                      <Input
                        id="station-code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        placeholder="000000"
                        value={code}
                        onChange={(event) => setCode(event.target.value.replace(/\D/g, "").slice(0, 6))}
                        required
                      />
                    </Field>
                    <Field>
                      <Button type="submit" disabled={loading || code.length !== 6}>
                        Verificar
                      </Button>
                    </Field>
                    <Button type="button" variant="outline" disabled={loading} onClick={handleResend}>
                      Reenviar código
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
