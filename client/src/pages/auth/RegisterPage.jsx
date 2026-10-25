import { useState } from "react"
import { Link, useNavigate } from "react-router"

import { InputPassword } from "@/components/common/inputs/InputPassword"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { ThemeToggle } from "@/components/layout/ThemeToggle"
import { Button } from "@/components/ui/button"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { register } from "@/lib/auth/auth"
import { notifyError } from "@/lib/notify"

// Cria só a conta. O acesso a empresa ou posto vem depois, na tela de contextos.
export default function RegisterPage() {
  const navigate = useNavigate()
  const [name, setName] = useState("")
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [passwordConfirmation, setPasswordConfirmation] = useState("")
  const [loading, setLoading] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setLoading(true)

    try {
      await register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })
      navigate("/auth/contextos", { replace: true })
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
            <div className="w-full max-w-xs">
              <form className="flex flex-col gap-6" onSubmit={handleSubmit}>
                <FieldGroup>
                  <div className="flex flex-col items-center gap-1 text-center">
                    <h1 className="text-2xl font-bold">Criar conta</h1>
                    <p className="text-sm text-balance text-muted-foreground">
                      Crie seu acesso na Logiv. Depois você cadastra seu posto ou é vinculado a uma empresa.
                    </p>
                  </div>
                  <Field>
                    <FieldLabel htmlFor="register-name">Nome</FieldLabel>
                    <Input
                      id="register-name"
                      placeholder="Ex: Pablo Alves"
                      value={name}
                      onChange={(event) => setName(event.target.value)}
                      autoComplete="name"
                      required
                    />
                  </Field>
                  <Field>
                    <FieldLabel htmlFor="register-email">E-mail</FieldLabel>
                    <Input
                      id="register-email"
                      type="email"
                      placeholder="usuario@empresa.com.br"
                      value={email}
                      onChange={(event) => setEmail(event.target.value)}
                      autoComplete="email"
                      required
                    />
                  </Field>
                  <Field>
                    <FieldLabel htmlFor="register-password">Senha</FieldLabel>
                    <InputPassword
                      id="register-password"
                      value={password}
                      onChange={setPassword}
                      placeholder="Mínimo de 8 caracteres"
                    />
                  </Field>
                  <Field>
                    <FieldLabel htmlFor="register-password-confirmation">Confirmação da senha</FieldLabel>
                    <InputPassword
                      id="register-password-confirmation"
                      value={passwordConfirmation}
                      onChange={setPasswordConfirmation}
                      placeholder="Repita a senha"
                    />
                  </Field>
                  <Field>
                    <Button type="submit" disabled={loading}>
                      {loading ? "Criando..." : "Criar conta"}
                    </Button>
                  </Field>
                  <p className="text-center text-sm text-muted-foreground">
                    Já tem conta?{" "}
                    <Link to="/auth/login" className="underline underline-offset-4 hover:text-foreground">
                      Entrar
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
