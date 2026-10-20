import { useEffect, useState } from "react"
import { useNavigate } from "react-router"

import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { AppLayout } from "@/layouts/AppLayout"
import { getAuthCompanies, me, selectCompany } from "@/lib/auth/auth"
import { SessionContext } from "@/lib/auth/session"

/**
 * /app só abre com token e empresa gravada nesse token.
 * Sem isso, o histórico não pode deixar a pessoa dentro do sistema.
 * A sessão (usuário, empresa atual e empresas) é carregada aqui e
 * compartilhada com o cabeçalho pelo SessionContext.
 */
export function RequireCompany() {
    const navigate = useNavigate()
    const [session, setSession] = useState(null)

    useEffect(() => {
        let active = true

        if (!localStorage.getItem("token")) {
            navigate("/auth/login", { replace: true })
            return () => {
                active = false
            }
        }

        Promise.all([me(), getAuthCompanies()])
            .then(([user, companies]) => {
                if (!active) return
                if (!user.has_company) {
                    navigate("/auth/empresas", { replace: true })
                    return
                }
                setSession({ user, companies })
            })
            .catch(() => {
                if (!active) return
                localStorage.removeItem("token")
                navigate("/auth/login", { replace: true })
            })

        return () => {
            active = false
        }
    }, [navigate])

    async function switchCompany(uuid) {
        if (uuid === session?.user?.current_company?.uuid) return
        await selectCompany(uuid)
        // Tudo em /app é escopado pela empresa; recarregar refaz todas as buscas.
        window.location.reload()
    }

    if (!session) {
        return <LoadingWrapper loading />
    }

    return (
        // Esse sessionContext.Provider faz parte do contexto global da aplicação.
        // Ele permite que qualquer componente filho acesse os dados da sessão.
        <SessionContext.Provider
            value={{
                user: session.user,
                currentCompany: session.user.current_company,
                companies: session.companies,
                switchCompany,
            }}
        >
            <AppLayout />
        </SessionContext.Provider>
    )
}
