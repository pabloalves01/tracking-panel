import { formatCpfCnpj } from '@/helpers/formatters'

/**
 * Quem exerce um papel como opção de busca: `{ value: uuid do papel,
 * label: nome, description: documento formatado, document, pix_key_type,
 * pix_key }`. Recebe a pessoa como a API devolve — no veículo ou na busca.
 */
export function roleOption(person) {
    if (!person) return null

    return {
        value: person.uuid,
        label: person.name,
        description: formatCpfCnpj(person.document),
        document: person.document,
        pix_key_type: person.pix_key_type ?? null,
        pix_key: person.pix_key ?? null,
    }
}
