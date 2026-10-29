import { createContext, useContext } from 'react'

/**
 * A sessão: quem está logado, em qual organização e quais outras ele tem.
 *
 * Carregada uma vez em `RequireOrganization` e lida pelo cabeçalho e pelo
 * menu. Antes havia dois contextos, um para empresa e outro para posto.
 */
export const SessionContext = createContext(null)

export function useSession() {
    const context = useContext(SessionContext)

    if (!context) {
        throw new Error('useSession precisa estar dentro de RequireOrganization')
    }

    return context
}
