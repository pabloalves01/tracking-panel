import { useLocation } from 'react-router'

import { useSession } from '@/lib/auth/session'

/**
 * Proprietário e administrador escrevem no contexto ativo.
 * Membro só consulta. No painel da Logiv, o administrador do sistema escreve,
 * com ou sem papel de gestão no contexto. A rota vem do router: ler
 * window.location deixa o layout preso no primeiro endereço.
 */
export function useCanWrite() {
    const { user } = useSession()
    const { pathname } = useLocation()
    const onSystem = pathname.startsWith('/app/sistema')

    if (user?.is_system_admin && onSystem) {
        return true
    }

    return user?.current_role === 'owner' || user?.current_role === 'admin'
}
