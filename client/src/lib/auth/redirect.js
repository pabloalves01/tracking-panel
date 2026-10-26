/**
 * Só aceita caminho interno. Bloqueia protocolo e URL absoluta.
 */
export function safeInternalPath(value) {
    if (typeof value !== 'string') {
        return null
    }

    if (!value.startsWith('/') || value.startsWith('//') || value.includes('\\')) {
        return null
    }

    return value
}
