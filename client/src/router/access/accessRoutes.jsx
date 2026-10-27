import CompanyInvitePage from '@/pages/access/CompanyInvitePage'
import CompanyMembersPage from '@/pages/access/CompanyMembersPage'

import { RequireManager } from '../RequireManager'

export default [
    {
        element: <RequireManager home="/app" />,
        children: [
            { path: 'usuarios', element: <CompanyMembersPage /> },
            { path: 'usuarios/convidar', element: <CompanyInvitePage /> },
        ],
    },
]
