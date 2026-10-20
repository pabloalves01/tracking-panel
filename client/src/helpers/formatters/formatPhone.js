/**
 * Formata telefone só para exibição.
 * O valor pode vir com ou sem pontuação.
 * Até 10 dígitos é fixo. Com 11 é celular.
 */
export function formatPhone(value) {
    const digits = String(value ?? '').replace(/\D/g, '').slice(0, 11)

    if (!digits) {
        return ''
    }

    if (digits.length <= 10) {
        return digits
            .replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4})(\d)/, '$1-$2')
    }

    return digits
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d)/, '$1-$2')
}
