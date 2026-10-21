import { useEffect, useState } from "react"
import { useNavigate } from "react-router"

import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { StationLayout } from "@/layouts/StationLayout"
import { me } from "@/lib/auth/auth"
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

        me()
            .then((user) => {
                if (!active) return
                if (!user.has_fuel_station) {
                    navigate("/auth/contextos", { replace: true })
                    return
                }
                setSession({ user, station: user.current_fuel_station })
            })
            .catch((error) => {
                if (!active) return
                const status = error.response?.status
                if (status === 401 || status === 403) {
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

    if (!session) {
        return <LoadingWrapper loading={!failed} />
    }

    return (
        <SessionContext.Provider value={{ user: session.user }}>
            <StationSessionContext.Provider value={{ station: session.station }}>
                <StationLayout />
            </StationSessionContext.Provider>
        </SessionContext.Provider>
    )
}
