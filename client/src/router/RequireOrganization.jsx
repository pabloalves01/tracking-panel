import { useEffect, useState } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router'

import { Spinner } from '@/components/ui/spinner'
import { SessionContext } from '@/lib/auth/session'
import { me } from '@/lib/auth/auth'

/**
 * Exige sessão e uma organização selecionada.
 *
 * Substitui `RequireCompany`, `RequireFuelStation` e `RequireManager`. Os
 * dois primeiros eram o mesmo arquivo duas vezes; o terceiro checava papel,
 * e papéis não existem mais.
 *
 * `type` restringe a área: `/app` exige uma empresa, `/posto` exige um
 * posto. Entrar na área errada manda para o seletor, não para um 403.
 */
export function RequireOrganization({ type }) {
    const location = useLocation()
    const [session, setSession] = useState(null)
    const [state, setState] = useState('loading')

    useEffect(() => {
        let active = true

        me()
            .then((data) => {
                if (!active) return

                setSession(data)
                setState(data.current_organization ? 'ready' : 'no-organization')
            })
            .catch(() => active && setState('unauthenticated'))

        return () => {
            active = false
        }
    }, [])

    if (state === 'loading') {
        return (
            <div className="flex min-h-svh items-center justify-center">
                <Spinner />
                <span className="sr-only">Carregando</span>
            </div>
        )
    }

    if (state === 'unauthenticated') {
        const redirect = encodeURIComponent(location.pathname + location.search)

        return <Navigate to={`/auth/login?redirect=${redirect}`} replace />
    }

    if (state === 'no-organization') {
        return <Navigate to="/auth/organizacoes" replace />
    }

    // Organização do tipo errado para esta área: o seletor resolve, mostrando
    // onde a pessoa pode de fato entrar.
    if (type && session.current_organization.type !== type) {
        return <Navigate to="/auth/organizacoes" replace />
    }

    return (
        <SessionContext.Provider value={session}>
            <Outlet />
        </SessionContext.Provider>
    )
}
