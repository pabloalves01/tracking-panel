import { LayoutToolbar } from '@/components/common/LayoutToolbar'

export default function FleetPage() {
    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Frota' },
                ]}
            />
            <div>Frota</div>
        </>
    )
}
