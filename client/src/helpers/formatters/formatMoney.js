/**
 * Formata um valor numérico como dinheiro brasileiro, só para exibição.
 * `12.34` vira `R$ 12,34`. `1234.56` vira `R$ 1.234,56`.
 * Vazio não vira zero: a tela usa isso para manter o placeholder.
 */
export function formatMoney(value) {
    if (value === null || value === undefined || value === '') {
        return ''
    }

    const amount = Number(value)

    if (!Number.isFinite(amount)) {
        return ''
    }

    const negative = amount < 0
    const [whole, cents] = Math.abs(amount).toFixed(2).split('.')
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.')

    return `${negative ? '-' : ''}R$ ${grouped},${cents}`
}
