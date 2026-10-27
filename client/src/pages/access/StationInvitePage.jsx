import InviteFormPage from '@/pages/access/InviteFormPage'
import { companyRoleOptions } from '@/helpers/labels/roleLabel'

export default function StationInvitePage() {
    return (
        <InviteFormPage
            breadcrumbs={[
                { text: 'Início', to: '/posto' },
                { text: 'Usuários', to: '/posto/usuarios' },
                { text: 'Novo usuário' },
            ]}
            listPath="/posto/usuarios"
            apiBase="/station"
            roleOptions={companyRoleOptions}
        />
    )
}
