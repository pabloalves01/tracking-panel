/**
 * Os papéis que uma entidade pode exercer.
 *
 * É o espelho do enum `App\Enums\EntityRole` no backend. Cada papel tem a
 * própria rota na API (`endpoint`); as telas de lista e formulário são as
 * mesmas para todos, e o que for só de um papel entra por aqui — como a aba
 * de CNH do motorista (`hasDetails`).
 *
 * `article` e `past` existem para a concordância das mensagens: "Cliente
 * desativado" e "Transportadora desativada" saem da mesma linha de código.
 */
export const ENTITY_ROLES = {
    driver: {
        role: 'driver',
        endpoint: '/registry/drivers',
        slug: 'motoristas',
        label: 'Motorista',
        plural: 'Motoristas',
        article: 'o',
        indefinite: 'um',
        hasDetails: true,
    },
    vehicle_owner: {
        role: 'vehicle_owner',
        endpoint: '/registry/vehicle-owners',
        slug: 'proprietarios',
        label: 'Proprietário',
        plural: 'Proprietários',
        article: 'o',
        indefinite: 'um',
    },
    vehicle_responsible: {
        role: 'vehicle_responsible',
        endpoint: '/registry/vehicle-responsibles',
        slug: 'responsaveis',
        label: 'Responsável',
        plural: 'Responsáveis',
        article: 'o',
        indefinite: 'um',
    },
    employee: {
        role: 'employee',
        endpoint: '/registry/employees',
        slug: 'funcionarios',
        label: 'Funcionário',
        plural: 'Funcionários',
        article: 'o',
        indefinite: 'um',
    },
    supplier: {
        role: 'supplier',
        endpoint: '/registry/suppliers',
        slug: 'fornecedores',
        label: 'Fornecedor',
        plural: 'Fornecedores',
        article: 'o',
        indefinite: 'um',
    },
    customer: {
        role: 'customer',
        endpoint: '/registry/customers',
        slug: 'clientes',
        label: 'Cliente',
        plural: 'Clientes',
        article: 'o',
        indefinite: 'um',
    },
    carrier: {
        role: 'carrier',
        endpoint: '/registry/carriers',
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
