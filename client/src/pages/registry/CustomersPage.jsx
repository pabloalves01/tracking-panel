import { LayoutToolbar } from '@/components/common/LayoutToolbar'

export default function CustomersPage() {
    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Entidades', to: '/app/cadastros/entidades' },
                    { text: 'Clientes' },
                ]}
            />
            <div>Clientes</div>
        </>
    )
}
