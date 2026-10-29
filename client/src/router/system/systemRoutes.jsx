import OrganizationFormPage from '@/pages/system/OrganizationFormPage'
import OrganizationsPage from '@/pages/system/OrganizationsPage'
import SystemPage from '@/pages/system/SystemPage'

export default [
    {
        path: 'sistema',
        children: [
            { index: true, element: <SystemPage /> },
            { path: 'organizacoes', element: <OrganizationsPage /> },
            { path: 'organizacoes/nova', element: <OrganizationFormPage /> },
            { path: 'organizacoes/:uuid/editar', element: <OrganizationFormPage /> },

            // Endereço antigo.
            { path: 'empresas', element: <OrganizationsPage /> },
        ],
    },
]
