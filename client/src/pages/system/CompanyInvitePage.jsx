import { useParams } from 'react-router'

import InviteFormPage from '@/pages/access/InviteFormPage'
import { systemRoleOptions } from '@/helpers/labels/roleLabel'

export default function CompanyInvitePage() {
    const { uuid } = useParams()
    const listPath = `/app/sistema/empresas/${uuid}/usuarios`

    return (
        <InviteFormPage
            breadcrumbs={[
                { text: 'Início', to: '/app' },
                { text: 'Sistema', to: '/app/sistema' },
                { text: 'Empresas', to: '/app/sistema/empresas' },
                { text: 'Usuários', to: listPath },
                { text: 'Convidar' },
            ]}
            listPath={listPath}
            apiBase={`/system/companies/${uuid}`}
            roleOptions={systemRoleOptions}
        />
    )
}
