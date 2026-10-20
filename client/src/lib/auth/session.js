import { createContext, useContext } from 'react'

/**
 * Sessão do usuário logado: dados pessoais, empresa atual e empresas disponíveis.
 * Carregada uma vez em RequireCompany e lida pelo cabeçalho.
 */
export const SessionContext = createContext(null)

export function useSession() {
    const context = useContext(SessionContext)

    if (!context) {
        throw new Error('useSession precisa estar dentro de RequireCompany')
    }

    return context
}
