import { PlusIcon } from 'lucide-react'

import { LayoutToolbar } from '@/components/common/LayoutToolbar'

const listPath = '/app/cadastros/frota/veiculos'

export default function VehiclesPage() {
    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Frota', to: '/app/cadastros/frota' },
                    { text: 'Veículos' },
                ]}
                buttons={[
                    { title: 'Novo veículo', icon: PlusIcon, to: `${listPath}/novo` },
                ]}
            />
            <div>
                Lista de veículos da empresa. Aqui vão aparecer a placa, o proprietário, o responsável e a situação de cada veículo da frota.
            </div>
        </>
    )
}
