/**
 * Os papéis que uma entidade pode exercer.
 *
 * É o espelho do enum `App\Enums\EntityRole` no backend. Um papel novo é uma
 * entrada aqui e um `case` lá — nenhuma tela, rota ou serviço precisa nascer.
 *
 * `article` e `past` existem para a concordância das mensagens: "Cliente
 * desativado" e "Transportadora desativada" saem da mesma linha de código.
 */
export const ENTITY_ROLES = {
    driver: {
        role: 'driver',
        slug: 'motoristas',
        label: 'Motorista',
        plural: 'Motoristas',
        article: 'o',
        indefinite: 'um',
        hasDetails: true,
    },
    vehicle_owner: {
        role: 'vehicle_owner',
        slug: 'proprietarios',
        label: 'Proprietário',
        plural: 'Proprietários',
        article: 'o',
        indefinite: 'um',
    },
    vehicle_responsible: {
        role: 'vehicle_responsible',
        slug: 'responsaveis',
        label: 'Responsável',
        plural: 'Responsáveis',
        article: 'o',
        indefinite: 'um',
    },
    employee: {
        role: 'employee',
        slug: 'funcionarios',
        label: 'Funcionário',
        plural: 'Funcionários',
        article: 'o',
        indefinite: 'um',
    },
    supplier: {
        role: 'supplier',
        slug: 'fornecedores',
        label: 'Fornecedor',
        plural: 'Fornecedores',
        article: 'o',
        indefinite: 'um',
    },
    customer: {
        role: 'customer',
        slug: 'clientes',
        label: 'Cliente',
        plural: 'Clientes',
        article: 'o',
        indefinite: 'um',
    },
    carrier: {
        role: 'carrier',
        slug: 'transportadoras',
        label: 'Transportadora',
        plural: 'Transportadoras',
        article: 'a',
        indefinite: 'uma',
        feminine: true,
    },
}

export const ROLE_LIST = Object.values(ENTITY_ROLES)

/** Resolve o papel a partir do segmento da URL. */
export function roleFromSlug(slug) {
    return ROLE_LIST.find((item) => item.slug === slug) ?? null
}

/** Flexiona o particípio conforme o gênero do papel. */
export function inflect(config, participle) {
    return config.feminine ? `${participle}a` : `${participle}o`
}

export function rolePath(config) {
    return `/app/cadastros/entidades/${config.slug}`
}
