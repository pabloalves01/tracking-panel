import { LayoutToolbar } from '@/components/common/LayoutToolbar'

export default function EmployeesPage() {
    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Entidades', to: '/app/cadastros/entidades' },
                    { text: 'Funcionários' },
                ]}
            />
            <div>Funcionários</div>
        </>
    )
}
