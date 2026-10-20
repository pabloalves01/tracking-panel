import { LayoutToolbar } from '@/components/common/LayoutToolbar'

export default function CarriersPage() {
    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Entidades', to: '/app/cadastros/entidades' },
                    { text: 'Transportadoras' },
                ]}
            />
            <div>Transportadoras</div>
        </>
    )
}
