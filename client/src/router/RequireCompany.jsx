import { useEffect, useState } from "react"
import { useNavigate } from "react-router"

import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { AppLayout } from "@/layouts/AppLayout"
import { me } from "@/lib/auth/auth"
import { SessionContext } from "@/lib/auth/session"

/**
 * /app só abre com token e empresa gravada nesse token.
 * Sem isso, o histórico não pode deixar a pessoa dentro do sistema.
 * A sessão (usuário e empresa atual) é carregada aqui e
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

        me()
            .then((user) => {
                if (!active) return
                // Admin do sistema entra sem empresa. Contexto de posto volta pra seleção.
                if (!user.has_company && (!user.is_system_admin || user.has_fuel_station)) {
                    navigate("/auth/contextos", { replace: true })
                    return
                }
                setSession({ user })
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
            }}
        >
            <AppLayout />
        </SessionContext.Provider>
    )
}
