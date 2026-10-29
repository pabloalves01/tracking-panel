import { useEffect, useState } from "react"
import { Link, useSearchParams } from "react-router"

import { AccountGate } from "@/components/auth/AccountGate"
import { InputCpfCnpj } from "@/components/common/inputs/InputCpfCnpj"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { ThemeToggle } from "@/components/layout/ThemeToggle"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { formatCpfCnpj } from "@/helpers/formatters"
import { logout, me } from "@/lib/auth/auth"
import { lookupFuelStationCnpj, storeFuelStationForUser } from "@/lib/auth/fuelStationSignup"
import { notifyError, notifySuccess } from "@/lib/notify"

function onlyDigits(value) {
  return String(value ?? "").replace(/\D/g, "").slice(0, 14)
}

/**
 * Cadastrar um posto.
 *
 *   CNPJ → logado?  sim → confirmar na conta atual
 *                   não → quem é você (AccountGate) → criar
 *
 * O posto só nasce para uma conta já autenticada. Antes esta tela pedia
 * nome, e-mail e senha juntos, antes de o sistema saber se a pessoa já tinha
 * conta — e quem administrava uma empresa digitava uma senha nova para nada,
 * e depois esperava um código que não vinha.
 */
export default function RegisterFuelStationPage() {
  const [params] = useSearchParams()
  const [step, setStep] = useState("cnpj")
  const [loading, setLoading] = useState(() => Boolean(localStorage.getItem("token")))
  const [user, setUser] = useState(null)
  const [cnpj, setCnpj] = useState(() => onlyDigits(params.get("cnpj")))
  const [fuelStation, setFuelStation] = useState(null)

  const cnpjReady = fuelStation?.document === cnpj
  const backHere = `/auth/register/posto?cnpj=${cnpj}`

  useEffect(() => {
    if (!localStorage.getItem("token")) return undefined

    let active = true
    me()
      .then((session) => {
        if (active) setUser(session.user)
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
      setStep(user ? "confirm" : "account")
      return
    }

    setLoading(true)

    try {
      setFuelStation(await lookupFuelStationCnpj(cnpj))
    } catch (error) {
      setFuelStation(null)
      notifyError(error)
    } finally {
      setLoading(false)
    }
  }

  /**
   * Daqui em diante a pessoa já está logada. Se o posto falhar — o CNPJ foi
   * cadastrado por outro enquanto isso, a Receita caiu —, a tela fica na
   * confirmação, e tentar de novo não pede identidade outra vez.
   */
  async function createFuelStation() {
    setLoading(true)

    try {
      const result = await storeFuelStationForUser(fuelStation.document)
      notifySuccess(result)
      window.location.assign(result.redirect_to ?? "/posto")
    } catch (error) {
      notifyError(error)
      setStep("confirm")
      setLoading(false)
    }
  }

  async function handleAuthenticated(session) {
    setUser(session.user)
    await createFuelStation()
  }

  async function switchAccount() {
    setLoading(true)

    try {
      await logout()
    } catch {
      localStorage.removeItem("token")
    }

    setUser(null)
    setStep("account")
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
              {step === "cnpj" ? (
                <form className="flex flex-col gap-6" onSubmit={handleCnpjSubmit}>
                  <FieldGroup>
                    <div className="flex flex-col items-center gap-1 text-center">
                      <h1 className="text-2xl font-bold">Cadastrar posto</h1>
                      <p className="text-sm text-balance text-muted-foreground">
                        Informe o CNPJ. Os dados vêm da Receita Federal.
                      </p>
                    </div>
                    <Field>
                      <FieldLabel htmlFor="fuel-station-cnpj">CNPJ</FieldLabel>
                      <InputCpfCnpj
                        id="fuel-station-cnpj"
                        type="cnpj"
                        value={cnpj}
                        onChange={(value) => {
                          setCnpj(value)
                          setFuelStation(null)
                        }}
                        autoFocus
                      />
                    </Field>
                    {cnpjReady ? <FuelStationSummary fuelStation={fuelStation} /> : null}
                    <Button type="submit" disabled={loading || cnpj.length !== 14}>
                      {cnpjReady ? "Continuar" : "Consultar CNPJ"}
                    </Button>
                    <p className="text-center text-sm text-muted-foreground">
                      <Link
                        to={user ? "/auth/organizacoes" : "/auth/login"}
                        className="underline underline-offset-4 hover:text-foreground"
                      >
                        {user ? "Voltar para as organizações" : "Voltar para o login"}
                      </Link>
                    </p>
                  </FieldGroup>
                </form>
              ) : null}

              {step === "account" ? (
                <div className="flex flex-col gap-6">
                  <FuelStationSummary fuelStation={fuelStation} compact />
                  <AccountGate
                    title="Quem vai administrar o posto?"
                    forgotBack={backHere}
                    onAuthenticated={handleAuthenticated}
                  />
                  <Button type="button" variant="ghost" onClick={() => setStep("cnpj")}>
                    Voltar
                  </Button>
                </div>
              ) : null}

              {step === "confirm" ? (
                <div className="flex flex-col gap-6">
                  <div className="flex flex-col items-center gap-1 text-center">
                    <h1 className="text-2xl font-bold">Cadastrar na sua conta</h1>
                    <p className="text-sm text-balance text-muted-foreground">
                      O posto entra na conta de <span className="text-foreground">{user?.name}</span> ({user?.email}).
                      Você será o primeiro membro.
                    </p>
                  </div>
                  <FuelStationSummary fuelStation={fuelStation} />
                  <Button type="button" onClick={createFuelStation} disabled={loading}>
                    Cadastrar posto
                  </Button>
                  <div className="flex flex-col items-center gap-2 text-sm text-muted-foreground">
                    <button type="button" className="underline-offset-4 hover:underline" onClick={() => setStep("cnpj")}>
                      Trocar o CNPJ
                    </button>
                    <button type="button" className="underline-offset-4 hover:underline" onClick={switchAccount}>
                      Não é você? Usar outra conta
                    </button>
                  </div>
                </div>
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

function FuelStationSummary({ fuelStation, compact = false }) {
  if (!fuelStation) return null

  const address = [
    fuelStation.street,
    fuelStation.street_number,
    fuelStation.complement,
    fuelStation.neighborhood,
    fuelStation.postal_code,
  ]
    .filter(Boolean)
    .join(", ")
  const place = [fuelStation.city, fuelStation.state].filter(Boolean).join("/")

  return (
    <Card size="sm">
      <CardContent className="flex flex-col gap-0.5 text-sm">
        <p className="font-medium">{fuelStation.trade_name || fuelStation.legal_name}</p>
        <p className="text-muted-foreground tabular-nums">{formatCpfCnpj(fuelStation.document)}</p>
        {compact ? null : (
          <>
            {fuelStation.trade_name ? <p className="text-muted-foreground">{fuelStation.legal_name}</p> : null}
            {address ? <p className="text-muted-foreground">{address}</p> : null}
            {place ? <p className="text-muted-foreground">{place}</p> : null}
          </>
        )}
      </CardContent>
    </Card>
  )
}
