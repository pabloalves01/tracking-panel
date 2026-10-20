import { LayoutToolbar } from '@/components/common/LayoutToolbar'

export default function VehiclesPage() {
    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Frota', to: '/app/cadastros/frota' },
                    { text: 'Veículos' },
                ]}
            />
            <div>Veículos</div>
        </>
    )
}
