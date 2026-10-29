import InviteFormPage from '@/pages/access/InviteFormPage'
import MembersPage from '@/pages/access/MembersPage'
import FuelStationCompaniesPage from '@/pages/fuelStation/FuelStationCompaniesPage'
import FuelStationHome from '@/pages/fuelStation/FuelStationHome'

/**
 * O painel do posto. Usuários e convites usam as mesmas telas da empresa —
 * a API é a mesma, da organização ativa.
 */
export default [
    { index: true, element: <FuelStationHome /> },
    { path: 'empresas', element: <FuelStationCompaniesPage /> },
    { path: 'usuarios', element: <MembersPage /> },
    { path: 'usuarios/convidar', element: <InviteFormPage /> },
]
