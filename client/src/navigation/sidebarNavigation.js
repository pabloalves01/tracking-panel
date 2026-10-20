import {
    BuildingIcon,
    HouseIcon,
    LayoutDashboardIcon,
    MapIcon,
    MonitorCog,
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

/**
 * `access` controla a visibilidade do módulo conforme a sessão:
 * - 'company': só aparece quando há empresa selecionada.
 * - 'system': só aparece para o administrador do sistema.
 */
export const modules = [
    {
        title: 'Painel',
        icon: LayoutDashboardIcon,
        access: 'company',
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
        access: 'company',
        items: [
            {
                title: 'Rastreamento',
                path: 'rastreamento',
                icon: MapIcon,
            },
        ],
    },
    {
        title: 'Sistema',
        icon: MonitorCog,
        access: 'system',
        items: [
            {
                title: 'Empresas',
                path: 'sistema/empresas',
                icon: BuildingIcon,
            },
        ],
    },

]
