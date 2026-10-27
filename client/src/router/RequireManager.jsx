import { useEffect, useRef } from 'react'
import { Navigate, Outlet } from 'react-router'

import { useSession } from '@/lib/auth/session'
import { notifyError } from '@/lib/notify'

/**
 * Usuários e convites só abrem para proprietário e administrador.
 * O membro que digita o endereço volta para o início, com aviso em português.
 */
export function RequireManager({ home }) {
    const { user } = useSession()
    const allowed = user?.current_role === 'owner' || user?.current_role === 'admin'
    const told = useRef(false)

    useEffect(() => {
        if (allowed || told.current) {
            return
        }

        told.current = true
        notifyError({
            response: {
                status: 403,
                data: { message: 'Você não gerencia os usuários deste contexto.' },
            },
        })
    }, [allowed])

    if (!allowed) {
        return <Navigate to={home} replace />
    }

    return <Outlet />
}
