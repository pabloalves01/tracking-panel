import { LayoutToolbar } from '@/components/common/LayoutToolbar'

export default function EntityPage() {
    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Entidades' },
                ]}
            />
            <div>Entidades</div>
        </>
    )
}
