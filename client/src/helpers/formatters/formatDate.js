/**
 * Data da API (ISO) em dd/mm/aaaa, só para exibição.
 */
export function formatDate(value) {
    if (!value) {
        return ''
    }

    return new Date(value).toLocaleDateString('pt-BR')
}
