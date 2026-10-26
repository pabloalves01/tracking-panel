import CompanyInvitePage from '@/pages/access/CompanyInvitePage'
import CompanyMembersPage from '@/pages/access/CompanyMembersPage'

export default [
    { path: 'usuarios', element: <CompanyMembersPage /> },
    { path: 'usuarios/convidar', element: <CompanyInvitePage /> },
]
