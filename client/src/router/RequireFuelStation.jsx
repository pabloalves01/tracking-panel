import { useEffect, useState } from "react"
import { useNavigate } from "react-router"

import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { StationLayout } from "@/layouts/StationLayout"
import { me } from "@/lib/auth/auth"
import { StationSessionContext } from "@/lib/auth/stationSession"
import { getStation } from "@/lib/station/station"

/**
 * /posto só abre com token e posto gravado nesse token.
 */
export function RequireFuelStation() {
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

        Promise.all([me(), getStation()])
            .then(([user, station]) => {
                if (!active) return
                if (!user.has_fuel_station) {
                    navigate("/auth/contextos", { replace: true })
                    return
                }
                setSession({ user, station })
            })
            .catch((error) => {
                if (!active) return
                const status = error.response?.status
                if (status === 409 || status === 403) {
                    navigate("/auth/contextos", { replace: true })
                    return
                }
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
        <StationSessionContext.Provider value={{ user: session.user, station: session.station }}>
            <StationLayout />
        </StationSessionContext.Provider>
    )
}
