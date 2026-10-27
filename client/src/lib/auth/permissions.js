import { useSession } from '@/lib/auth/session'

/**
 * Proprietário e administrador escrevem no contexto ativo.
 * Membro só consulta. No painel da Logiv, o administrador do sistema escreve.
 */
export function useCanWrite() {
    const { user } = useSession()
    const onSystem = typeof window !== 'undefined' && window.location.pathname.startsWith('/app/sistema')

    if (user?.is_system_admin && onSystem) {
        return true
    }

    return user?.current_role === 'owner' || user?.current_role === 'admin'
}
