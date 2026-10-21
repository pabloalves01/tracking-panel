import {
    ArrowDownToLineIcon,
    ArrowUpFromLineIcon,
    BoxesIcon,
    BuildingIcon,
    ClipboardListIcon,
    ContactIcon,
    FileTextIcon,
    IdCardIcon,
    LandmarkIcon,
    LayoutDashboardIcon,
    MapIcon,
    MapPinIcon,
    PackageIcon,
    ReceiptIcon,
    ShipIcon,
    ShoppingCartIcon,
    FuelIcon,
    TruckIcon,
    UserIcon,
    UsersIcon,
    WalletIcon,
    WarehouseIcon,
    WrenchIcon,
} from 'lucide-react'

/**
 * Navegação da sidebar. As rotas ficam em `src/router`, um arquivo por módulo.
 *
 * `path` da home é `/app`. Nos demais itens, `path` é o trecho depois de `/app`.
 * Exemplo: `financeiro/transacoes` vira `/app/financeiro/transacoes`.
 *
 * `access` controla a visibilidade do grupo conforme a sessão:
 * - 'company': só aparece quando há empresa selecionada.
 * - 'system': só aparece para o administrador do sistema.
 */
export const app = {
    name: 'Logiv',
    footerName: 'logiv',
    version: '1.0',
    revision: 'd46498f',
}

export const navigation = [
    {
        label: 'GERAL',
        access: 'company',
        items: [
            {
                title: 'Dashboard',
                path: '/app',
                icon: LayoutDashboardIcon,
            },
        ],
    },
    {
        label: 'OPERACIONAL',
        access: 'company',
        items: [
            {
                title: 'Operações logísticas',
                path: 'operacoes',
                icon: TruckIcon,
                children: [
                    { title: 'Portuárias', path: 'portuarias', icon: ShipIcon },
                    { title: 'Rodoviárias', path: 'rodoviarias', icon: TruckIcon },
                    { title: 'Rastreamento', path: 'rastreamento', icon: MapIcon },
                ],
            },
        ],
    },
    {
        label: 'GESTÃO',
        access: 'company',
        items: [
            {
                title: 'Financeiro',
                path: 'financeiro',
                icon: LandmarkIcon,
                children: [
                    {
                        title: 'Transações',
                        path: 'transacoes',
                        icon: WalletIcon,
                        children: [
                            { title: 'Contas a pagar', path: 'contas-a-pagar', icon: ArrowUpFromLineIcon },
                            { title: 'Contas a receber', path: 'contas-a-receber', icon: ArrowDownToLineIcon },
                        ],
                    },
                    {
                        title: 'Relatórios',
                        path: 'relatorios',
                        icon: FileTextIcon,
                        children: [
                            { title: 'Fluxo de caixa', path: 'fluxo-de-caixa', icon: FileTextIcon },
                            { title: 'DRE', path: 'dre', icon: FileTextIcon },
                        ],
                    },
                ],
            },
            {
                title: 'Compras',
                path: 'compras',
                icon: ShoppingCartIcon,
                children: [
                    { title: 'Ordem de compra', path: 'ordem-de-compra', icon: ClipboardListIcon },
                    { title: 'Ordem de serviço', path: 'ordem-de-servico', icon: WrenchIcon },
                ],
            },
            {
                title: 'Estoque',
                path: 'estoque',
                icon: WarehouseIcon,
                children: [
                    { title: 'Produtos', path: 'produtos', icon: PackageIcon },
                    { title: 'Depósitos', path: 'depositos', icon: BoxesIcon },
                    { title: 'Endereços', path: 'enderecos', icon: MapPinIcon },
                ],
            },
            {
                title: 'Cadastros',
                path: 'cadastros',
                icon: ContactIcon,
                children: [
                    {
                        title: 'Entidades',
                        path: 'entidades',
                        icon: UsersIcon,
                        children: [
                            { title: 'Fornecedores', path: 'fornecedores', icon: UsersIcon },
                            { title: 'Clientes', path: 'clientes', icon: UsersIcon },
                            { title: 'Funcionários', path: 'funcionarios', icon: IdCardIcon },
                            { title: 'Transportadoras', path: 'transportadoras', icon: TruckIcon },
                            { title: 'Motoristas', path: 'motoristas', icon: UserIcon },
                            { title: 'Proprietários', path: 'proprietarios', icon: UserIcon },
                            { title: 'Responsáveis', path: 'responsaveis', icon: UserIcon },
                            { title: 'Postos', path: 'postos', icon: FuelIcon },
                        ],
                    },
                    {
                        title: 'Frota',
                        path: 'frota',
                        icon: TruckIcon,
                        children: [
                            { title: 'Veículos', path: 'veiculos', icon: TruckIcon },
                        ],
                    },
                ],
            },
            {
                title: 'Faturamento',
                path: 'faturamento',
                icon: ReceiptIcon,
                children: [
                    {
                        title: 'Faturas',
                        path: 'faturas',
                        icon: ReceiptIcon,
                        children: [
                            { title: 'Pagar', path: 'pagar', icon: ArrowUpFromLineIcon },
                            { title: 'Receber', path: 'receber', icon: ArrowDownToLineIcon },
                        ],
                    },
                    {
                        title: 'Pré-faturas',
                        path: 'pre-faturas',
                        icon: FileTextIcon,
                        children: [
                            { title: 'Pagar', path: 'pagar', icon: ArrowUpFromLineIcon },
                            { title: 'Receber', path: 'receber', icon: ArrowDownToLineIcon },
                        ],
                    },
                ],
            },
        ],
    },
    {
        label: 'Sistema',
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

/** Junta o caminho do pai com o do filho: 'financeiro' + 'transacoes' = 'financeiro/transacoes'. */
export function joinPath(parent, child) {
    if (!parent || parent === '/app') return child
    return `${parent.replace(/\/+$/, '')}/${child.replace(/^\/+/, '')}`
}
