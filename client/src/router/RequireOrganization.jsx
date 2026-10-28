import { useCallback, useEffect, useState } from 'react'
import { Navigate, useLocation } from 'react-router'

import { AppLayout } from '@/layouts/AppLayout'
import { FuelStationLayout } from '@/layouts/FuelStationLayout'
import { Spinner } from '@/components/ui/spinner'
import { SessionContext } from '@/lib/auth/session'
import { me } from '@/lib/auth/auth'

const layouts = {
    company: AppLayout,
    fuel_station: FuelStationLayout,
}

/**
 * Exige sessão e uma organização do tipo da área.
 *
 * Substitui `RequireCompany`, `RequireFuelStation` e `RequireManager` — os
 * dois primeiros eram o mesmo arquivo duas vezes, e o terceiro checava
 * papel, que não existe mais.
 *
 * O admin do sistema entra em `/app` sem organização nenhuma: as telas de
 * Sistema moram ali, e ele não pertence a empresa alguma. O menu já esconde
 * os grupos de empresa quando não há organização, então sobra o que ele de
 * fato pode usar.
 */
export function RequireOrganization({ type }) {
    const location = useLocation()
    const [session, setSession] = useState(null)
    const [state, setState] = useState(() => (localStorage.getItem('token') ? 'loading' : 'unauthenticated'))

    /**
     * Relê a sessão sem sair da área.
     *
     * A troca entre organizações do mesmo tipo — empresa para empresa — não
     * desmonta este componente, e sem isto o cabeçalho seguia mostrando a
     * organização anterior até um F5.
     */
    const reload = useCallback(async () => {
        const data = await me()
        setSession(data)

        return data
    }, [])

    useEffect(() => {
        if (!localStorage.getItem('token')) return undefined

        let active = true

        me()
            .then((data) => {
                if (!active) return

                setSession(data)

                const organization = data.current_organization
                const isSystemArea = type === 'company' && data.user?.is_system_admin

                if (organization?.type === type) {
                    setState('ready')
                } else if (!organization && isSystemArea) {
                    // Admin do sistema sem organização: entra para administrar.
                    setState('ready')
                } else {
                    setState('wrong-organization')
                }
            })
            .catch(() => {
                if (!active) return

                localStorage.removeItem('token')
                setState('unauthenticated')
            })

        return () => {
            active = false
        }
    }, [type])

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

    // Sem organização, ou de um tipo que não é o desta área: o seletor
    // resolve os dois, mostrando onde a pessoa pode de fato entrar.
    if (state === 'wrong-organization') {
        return <Navigate to="/auth/organizacoes" replace />
    }

    const Layout = layouts[type] ?? AppLayout

    // A chave é a organização: trocar de organização remonta a área inteira,
    // e toda tela busca seus dados de novo. Sem isso, uma listagem aberta
    // continuaria mostrando os dados da empresa anterior.
    return (
        <SessionContext.Provider value={{ ...session, reload }}>
            <Layout key={session.current_organization?.uuid ?? 'sistema'} />
        </SessionContext.Provider>
    )
}
