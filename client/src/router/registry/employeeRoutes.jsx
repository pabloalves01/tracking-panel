import EmployeeFormPage from '@/pages/registry/EmployeeFormPage'
import EmployeesPage from '@/pages/registry/EmployeesPage'

export default [
    { path: 'funcionarios', element: <EmployeesPage /> },
    { path: 'funcionarios/novo', element: <EmployeeFormPage /> },
    { path: 'funcionarios/:uuid/editar', element: <EmployeeFormPage /> },
]
