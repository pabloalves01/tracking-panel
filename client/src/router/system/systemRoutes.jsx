import SystemPage from "@/pages/system/SystemPage"
import CompaniesPage from "@/pages/system/CompaniesPage"
import CompanyFormPage from "@/pages/system/CompanyFormPage"
export default [
    {
        path: 'sistema',
        children: [
            {
                path: '',
                element: <SystemPage />,
            },
            {
                path: 'empresas',
                element: <CompaniesPage />,
            },
            {
                path: 'empresas/novo',
                element: <CompanyFormPage />,
            },
            {
                path: 'empresas/:uuid/editar',
                element: <CompanyFormPage />,
            }
        ],
    },
]