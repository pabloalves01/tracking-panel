import { Navigate } from 'react-router'

import Home from '@/pages/Home'
import { useSession } from '@/lib/auth/session'

/**
 * O que abre em `/app`.
 *
 * O admin do sistema entra aqui sem organização — as telas de Sistema moram
 * sob `/app`. Para ele, o dashboard de empresa não diz nada, então a porta é
 * a lista de organizações.
 */
export default function AppHome() {
    const { current_organization: organization } = useSession()

    if (!organization) {
        return <Navigate to="/app/sistema/organizacoes" replace />
    }

    return <Home />
}
