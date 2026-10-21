/**
 * Formata um valor numérico como dinheiro brasileiro, só para exibição.
 * `12.34` vira `R$ 12,34`. `1234.56` vira `R$ 1.234,56`.
 * `decimals` define as casas depois da vírgula (2 por padrão, 3 no preço do combustível).
 * Vazio não vira zero: a tela usa isso para manter o placeholder.
 */
export function formatMoney(value, decimals = 2) {
    if (value === null || value === undefined || value === '') {
        return ''
    }

    const amount = Number(value)

    if (!Number.isFinite(amount)) {
        return ''
    }

    const places = Number.isInteger(decimals) && decimals >= 0 ? decimals : 2
    const negative = amount < 0
    const [whole, fraction = ''] = Math.abs(amount).toFixed(places).split('.')
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
    const cents = places === 0 ? '' : `,${fraction}`

    return `${negative ? '-' : ''}R$ ${grouped}${cents}`
}
