import { createContext, useContext } from 'react'

/**
 * Sessão do posto: usuário e posto gravado no token.
 * Carregada uma vez em RequireFuelStation e lida pelo cabeçalho.
 */
export const StationSessionContext = createContext(null)

export function useStationSession() {
    const context = useContext(StationSessionContext)

    if (!context) {
        throw new Error('useStationSession precisa estar dentro de RequireFuelStation')
    }
    return context
}
