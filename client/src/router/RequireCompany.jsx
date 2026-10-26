import { useEffect, useState } from "react"
import { useNavigate } from "react-router"

import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { AppLayout } from "@/layouts/AppLayout"
import { getAuthContexts, me, selectCompany, selectFuelStation } from "@/lib/auth/auth"
import { SessionContext } from "@/lib/auth/session"
import { notifyError } from "@/lib/notify"

/**
 * /app só abre com token e empresa gravada nesse token.
 * Sem isso, o histórico não pode deixar a pessoa dentro do sistema.
 * A sessão (usuário, empresa atual, empresas e postos) é carregada aqui.
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

        Promise.all([me(), getAuthContexts()])
            .then(([user, contexts]) => {
                if (!active) return
                // Admin do sistema entra sem empresa. Contexto de posto volta pra seleção.
                if (!user.has_company && (!user.is_system_admin || user.has_fuel_station)) {
                    navigate("/auth/contextos", { replace: true })
                    return
                }
                setSession({
                    user,
                    companies: contexts.companies ?? [],
                    stations: contexts.fuel_stations ?? [],
                })
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
        if (uuid === session?.user?.current_company?.uuid && !session?.user?.has_fuel_station) return
        try {
            await selectCompany(uuid)
            window.location.assign("/app")
        } catch (error) {
            notifyError(error)
        }
    }

    async function switchStation(uuid) {
        try {
            await selectFuelStation(uuid)
            window.location.assign("/posto")
        } catch (error) {
            notifyError(error)
        }
    }

    if (!session) {
        return <LoadingWrapper loading />
    }

    return (
        <SessionContext.Provider
            value={{
                user: session.user,
                currentCompany: session.user.current_company,
                companies: session.companies,
                stations: session.stations,
                switchCompany,
                switchStation,
            }}
        >
            <AppLayout />
        </SessionContext.Provider>
    )
}
