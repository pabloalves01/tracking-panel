import { useState } from "react"
import { Link, useNavigate } from "react-router"

import { InputCpfCnpj } from "@/components/common/inputs/InputCpfCnpj"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { ThemeToggle } from "@/components/layout/ThemeToggle"
import { Button } from "@/components/ui/button"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { lookupStationCnpj, storeStationSignup } from "@/lib/auth/stationSignup"
import { notifyError, notifySuccess } from "@/lib/notify"

// O posto é cadastrado por uma conta logada, que vira a responsável por ele.
export default function RegisterFuelStationPage() {
  const navigate = useNavigate()
  const [loggedIn] = useState(() => !!localStorage.getItem("token"))
  const [loading, setLoading] = useState(false)
  const [cnpj, setCnpj] = useState("")
  const [station, setStation] = useState(null)

  const cnpjReady = station?.cnpj === cnpj

  async function handleSubmit(event) {
    event.preventDefault()
    setLoading(true)

    try {
      if (!cnpjReady) {
        setStation(await lookupStationCnpj(cnpj))
        return
      }

      const result = await storeStationSignup({ cnpj: station.cnpj })
      notifySuccess(result)
      navigate("/posto", { replace: true })
    } catch (error) {
      if (!cnpjReady) setStation(null)
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
              {loggedIn ? (
                <form className="flex flex-col gap-6" onSubmit={handleSubmit}>
                  <FieldGroup>
                    <div className="flex flex-col items-center gap-1 text-center">
                      <h1 className="text-2xl font-bold">Cadastrar posto</h1>
                      <p className="text-sm text-balance text-muted-foreground">
                        Informe o CNPJ. Os dados da Receita Federal aparecem travados e o posto fica vinculado à sua conta.
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
                        {cnpjReady ? "Cadastrar posto" : "Consultar CNPJ"}
                      </Button>
                    </Field>
                    <p className="text-center text-sm text-muted-foreground">
                      <Link to="/auth/contextos" className="underline underline-offset-4 hover:text-foreground">
                        Voltar
                      </Link>
                    </p>
                  </FieldGroup>
                </form>
              ) : (
                <FieldGroup>
                  <div className="flex flex-col items-center gap-1 text-center">
                    <h1 className="text-2xl font-bold">Cadastrar posto</h1>
                    <p className="text-sm text-balance text-muted-foreground">
                      Para cadastrar um posto, entre na sua conta da Logiv. Se ainda não tem uma, crie agora e
                      depois cadastre o posto.
                    </p>
                  </div>
                  <Field>
                    <Button className="cursor-pointer" onClick={() => navigate("/auth/register")}>
                      Criar conta
                    </Button>
                  </Field>
                  <Field>
                    <Button variant="outline" className="cursor-pointer" onClick={() => navigate("/auth/login")}>
                      Já tenho conta
                    </Button>
                  </Field>
                </FieldGroup>
              )}
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
