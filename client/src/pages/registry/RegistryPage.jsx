import { LayoutToolbar } from '@/components/common/LayoutToolbar'

export default function RegistryPage() {
    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros' },
                ]}
            />
            <div>Cadastros</div>
        </>
    )
}
