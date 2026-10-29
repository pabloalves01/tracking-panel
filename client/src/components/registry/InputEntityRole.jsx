import { InputRecordSearch } from '@/components/common/inputs/InputRecordSearch'
import { getEntities } from '@/lib/registry/entities'
import { roleOption } from '@/lib/registry/roleOption'
import { ENTITY_ROLES } from '@/lib/registry/roles'

/**
 * Busca de quem exerce um papel — o proprietário, o responsável, o
 * transportador de um veículo — pelo nome ou documento. `value` é uma
 * opção de `roleOption`, ou null.
 */
export function InputEntityRole({ role, ...props }) {
    const config = ENTITY_ROLES[role]

    function search(term) {
        return getEntities(role, { search: term || undefined, per_page: 10 }).then((response) =>
            (response.data ?? []).map((record) => ({
                ...roleOption({ ...record.entity, uuid: record.uuid }),
                hint: record.status === 'inactive' ? 'Inativo' : undefined,
            }))
        )
    }

    return (
        <InputRecordSearch
            search={search}
            placeholder="Nome ou documento"
            emptyText={`Nenhum ${config.label.toLowerCase()} encontrado.`}
            {...props}
        />
    )
}
