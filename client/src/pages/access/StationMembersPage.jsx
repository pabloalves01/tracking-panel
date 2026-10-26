import MembersPage from '@/pages/access/MembersPage'
import { companyRoleOptions, ownerRoleOptions } from '@/helpers/labels/roleLabel'
import { useSession } from '@/lib/auth/session'

const listPath = '/posto/usuarios'

export default function StationMembersPage() {
    const { user } = useSession()
    const actorRole = user?.current_role
    const canManage = actorRole === 'owner' || actorRole === 'admin'

    return (
        <MembersPage
            breadcrumbs={[{ text: 'Início', to: '/posto' }, { text: 'Usuários' }]}
            listPath={listPath}
            apiBase="/station"
            roleOptions={actorRole === 'owner' ? ownerRoleOptions : companyRoleOptions}
            canManage={canManage}
            actorEmail={user?.email}
            actorRole={actorRole}
        />
    )
}
