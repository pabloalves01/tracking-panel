import { useNavigate } from 'react-router'

export default function SelectCompaniesPage() {
    const navigate = useNavigate()
    const companies = [
        { id: 1, name: "Empresa 1" },
        { id: 2, name: "Empresa 2" },
        { id: 3, name: "Empresa 3" },
    ]

    const handleCompanyClick = (id) => {
        console.log('empresa selecionada::::::', id)
        // aqui deve guardar a empresa nos cookies ou algo do tipo
        navigate('/app')
    }

    return (
        <div className="flex flex-col gap-2 justify-center items-center h-screen">
            <h1>Selecionar a empresa</h1>
            <div className="flex gap-2">
                {companies.map((company) => (
                    <CompanyCard key={company.id} name={company.name} onClick={() => handleCompanyClick(company.id)} />
                ))}
            </div>
        </div>
    )
}

function CompanyCard({ id, name, onClick }) {
    return (
        <div className="border border-gray-300 rounded-md p-2" onClick={onClick}>
            <h2>{name}</h2>
        </div>
    )
}