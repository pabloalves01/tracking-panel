import { useEffect, useState } from 'react'
import { useParams } from 'react-router'

import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import MembersPage from '@/pages/access/MembersPage'
import { systemRoleOptions } from '@/helpers/labels/roleLabel'
import { getCompany } from '@/lib/system/companies'
import { notifyError } from '@/lib/notify'

export default function CompanyMembersPage() {
    const { uuid } = useParams()
    const [company, setCompany] = useState(null)
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        getCompany(uuid)
            .then(setCompany)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [uuid])

    const listPath = `/app/sistema/empresas/${uuid}/usuarios`

    return (
        <LoadingWrapper loading={loading}>
            <MembersPage
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Sistema', to: '/app/sistema' },
                    { text: 'Empresas', to: '/app/sistema/empresas' },
                    { text: company?.trade_name || 'Empresa', to: listPath },
                    { text: 'Usuários' },
                ]}
                listPath={listPath}
                apiBase={`/system/companies/${uuid}`}
                roleOptions={systemRoleOptions}
                canManage
                actorRole="owner"
            />
        </LoadingWrapper>
    )
}
