import {
    HouseIcon,
    LayoutDashboardIcon,
    MapIcon,
    ShipIcon,
} from 'lucide-react'

/**
 * Rótulos da sidebar.
 *
 * `path` da home é `/app`, sem barra no final.
 * Nos demais itens, `path` é o trecho depois de `/app`.
 * Exemplo: aqui `rastreamento`, no router `path: 'rastreamento'`.
 * A URL final fica `/app/rastreamento`.
 *
 * Página sem layout não entra aqui. Ela fica solta no `router.jsx`.
 */
export const app = {
    name: 'Logiv',
    footerName: 'logiv',
    version: '1.0',
    revision: 'd46498f',
}

export const currentUser = {
    name: 'Pablo Alves',
    email: 'pablo.alves@logiv.com',
    initials: 'PA',
}

export const modules = [
    {
        title: 'Painel',
        icon: LayoutDashboardIcon,
        items: [
            {
                title: 'Início',
                path: '/app',
                icon: HouseIcon,
            },
        ],
    },
    {
        title: 'Operações',
        icon: ShipIcon,
        items: [
            {
                title: 'Rastreamento',
                path: 'rastreamento',
                icon: MapIcon,
            },
        ],
    },
]
