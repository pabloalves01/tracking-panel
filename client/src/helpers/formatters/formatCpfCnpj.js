/**
 * Formata CPF ou CNPJ só para exibição.
 * O valor pode vir com ou sem pontuação.
 * Até 11 dígitos é CPF. A partir de 12 é CNPJ.
 */
export function formatCpfCnpj(value) {
    const digits = String(value ?? '').replace(/\D/g, '')

    if (!digits) {
        return ''
    }

    if (digits.length <= 11) {
        return digits
            .slice(0, 11)
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2')
    }

    return digits
        .slice(0, 14)
        .replace(/(\d{2})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1/$2')
        .replace(/(\d{4})(\d{1,2})$/, '$1-$2')
}
