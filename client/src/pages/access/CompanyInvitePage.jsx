import InviteFormPage from '@/pages/access/InviteFormPage'
import { companyRoleOptions } from '@/helpers/labels/roleLabel'

export default function CompanyInvitePage() {
    return (
        <InviteFormPage
            breadcrumbs={[
                { text: 'Início', to: '/app' },
                { text: 'Usuários', to: '/app/usuarios' },
                { text: 'Novo usuário' },
            ]}
            listPath="/app/usuarios"
            apiBase="/company"
            roleOptions={companyRoleOptions}
        />
    )
}
