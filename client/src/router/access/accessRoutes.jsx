import InviteFormPage from '@/pages/access/InviteFormPage'
import MembersPage from '@/pages/access/MembersPage'

/**
 * Usuários e convites da organização ativa. Sem guard de papel: todo membro
 * gerencia, por enquanto.
 */
export default [
    { path: 'usuarios', element: <MembersPage /> },
    { path: 'usuarios/convidar', element: <InviteFormPage /> },
]
