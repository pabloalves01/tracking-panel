import { useEffect, useState } from "react"
import { useNavigate } from "react-router"

import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { StationLayout } from "@/layouts/StationLayout"
import { getAuthContexts, me, selectCompany, selectFuelStation } from "@/lib/auth/auth"
import { SessionContext } from "@/lib/auth/session"
import { StationSessionContext } from "@/lib/auth/stationSession"
import { notifyError } from "@/lib/notify"

/**
 * /posto só abre com token e posto gravado nesse token.
 */
export function RequireFuelStation() {
    const navigate = useNavigate()
    const [session, setSession] = useState(null)
    const [failed, setFailed] = useState(false)

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
                if (!user.has_fuel_station) {
                    navigate("/auth/contextos", { replace: true })
                    return
                }
                setSession({
                    user,
                    station: user.current_fuel_station,
                    companies: contexts.companies ?? [],
                    stations: contexts.fuel_stations ?? [],
                })
            })
            .catch((error) => {
                if (!active) return
                const status = error.response?.status
                const lost = error.response?.data?.code === "context_access_lost"
                if (lost) {
                    navigate("/auth/contextos", { replace: true })
                    return
                }
                if (status === 401) {
                    localStorage.removeItem("token")
                    navigate("/auth/login", { replace: true })
                    return
                }
                notifyError(error)
                setFailed(true)
            })

        return () => {
            active = false
        }
    }, [navigate])

    async function switchCompany(uuid) {
        try {
            await selectCompany(uuid)
            window.location.assign("/app")
        } catch (error) {
            notifyError(error)
        }
    }

    async function switchStation(uuid) {
        if (uuid === session?.station?.uuid) return
        try {
            await selectFuelStation(uuid)
            window.location.assign("/posto")
        } catch (error) {
            notifyError(error)
        }
    }

    if (!session) {
        return <LoadingWrapper loading={!failed} />
    }

    return (
        <SessionContext.Provider
            value={{
                user: session.user,
                currentCompany: null,
                companies: session.companies,
                stations: session.stations,
                switchCompany,
                switchStation,
            }}
        >
            <StationSessionContext.Provider value={{ station: session.station }}>
                <StationLayout />
            </StationSessionContext.Provider>
        </SessionContext.Provider>
    )
}
