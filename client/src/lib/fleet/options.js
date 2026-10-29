/**
 * Rótulos dos valores fixos da frota — espelho dos enums
 * `App\Enums\Fleet\…` no backend.
 */

/**
 * Veículos e implementos são o mesmo cadastro na API, em telas separadas.
 * `path` é a lista; `vehiclePath` monta a edição pelo grupo.
 */
export const FLEET_KINDS = {
    motor_vehicle: {
        kind: 'motor_vehicle',
        path: '/app/cadastros/frota/veiculos',
        plural: 'Veículos',
        singular: 'veículo',
        create: 'Novo veículo',
    },
    trailer: {
        kind: 'trailer',
        path: '/app/cadastros/frota/implementos',
        plural: 'Implementos',
        singular: 'implemento',
        create: 'Novo implemento',
    },
}

export function vehiclePath(kind, uuid, tab = null) {
    return `${FLEET_KINDS[kind].path}/${uuid}/editar${tab ? `?aba=${tab}` : ''}`
}

export const FLEET_TYPES = [
    { value: 'own', label: 'Frota própria' },
    { value: 'aggregated', label: 'Agregado' },
    { value: 'third_party', label: 'Terceiro' },
]

export const INSTALLATION_ROLES = [
    { value: 'primary', label: 'Principal' },
    { value: 'backup', label: 'Reserva' },
]

export const INSTALLATION_ROLE_LABELS = Object.fromEntries(
    INSTALLATION_ROLES.map((role) => [role.value, role.label])
)

export const ANTT_CATEGORIES = [
    { value: 'tac', label: 'TAC — Autônomo' },
    { value: 'etc', label: 'ETC — Empresa' },
    { value: 'ctc', label: 'CTC — Cooperativa' },
]

/**
 * As cores do CRLV (tabela do Denatran), com a amostra da bolinha.
 * `swatch` é CSS: a cor "Fantasia" é um gradiente.
 */
export const VEHICLE_COLORS = [
    { value: 'Amarela', swatch: '#facc15' },
    { value: 'Azul', swatch: '#2563eb' },
    { value: 'Bege', swatch: '#e7d8b8' },
    { value: 'Branca', swatch: '#ffffff' },
    { value: 'Cinza', swatch: '#9ca3af' },
    { value: 'Dourada', swatch: '#c9a227' },
    { value: 'Grená', swatch: '#7f1d1d' },
    { value: 'Laranja', swatch: '#f97316' },
    { value: 'Marrom', swatch: '#78350f' },
    { value: 'Prata', swatch: '#d1d5db' },
    { value: 'Preta', swatch: '#111827' },
    { value: 'Rosa', swatch: '#f472b6' },
    { value: 'Roxa', swatch: '#7e22ce' },
    { value: 'Verde', swatch: '#16a34a' },
    { value: 'Vermelha', swatch: '#dc2626' },
    { value: 'Fantasia', swatch: 'conic-gradient(#dc2626, #facc15, #16a34a, #2563eb, #7e22ce, #dc2626)' },
]
