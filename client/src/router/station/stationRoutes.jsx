import InviteFormPage from '@/pages/access/InviteFormPage'
import MembersPage from '@/pages/access/MembersPage'
import StationCompaniesPage from '@/pages/station/StationCompaniesPage'
import StationHome from '@/pages/station/StationHome'

/**
 * O painel do posto. Usuários e convites usam as mesmas telas da empresa —
 * a API é a mesma, da organização ativa.
 */
export default [
    { index: true, element: <StationHome /> },
    { path: 'empresas', element: <StationCompaniesPage /> },
    { path: 'usuarios', element: <MembersPage /> },
    { path: 'usuarios/convidar', element: <InviteFormPage /> },
]
