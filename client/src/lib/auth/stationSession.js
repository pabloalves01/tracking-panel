import { createContext, useContext } from 'react'

/**
 * Posto gravado no token.
 * Carregado uma vez em RequireFuelStation e lido pelo cabeçalho.
 */
export const StationSessionContext = createContext(null)

export function useStationSession() {
    const context = useContext(StationSessionContext)

    if (!context) {
        throw new Error('useStationSession precisa estar dentro de RequireFuelStation')
    }
    return context
}
