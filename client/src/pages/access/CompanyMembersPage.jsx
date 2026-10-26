import MembersPage from '@/pages/access/MembersPage'
import { companyRoleOptions, ownerRoleOptions } from '@/helpers/labels/roleLabel'
import { useSession } from '@/lib/auth/session'

const listPath = '/app/usuarios'

export default function CompanyMembersPage() {
    const { user } = useSession()
    const actorRole = user?.current_role
    const canManage = actorRole === 'owner' || actorRole === 'admin'

    return (
        <MembersPage
            breadcrumbs={[{ text: 'Início', to: '/app' }, { text: 'Usuários' }]}
            listPath={listPath}
            apiBase="/company"
            roleOptions={actorRole === 'owner' ? ownerRoleOptions : companyRoleOptions}
            canManage={canManage}
            actorEmail={user?.email}
            actorRole={actorRole}
        />
    )
}
