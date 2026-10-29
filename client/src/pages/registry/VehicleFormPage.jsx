import { LayoutToolbar } from '@/components/common/LayoutToolbar'

const listPath = '/app/cadastros/frota/veiculos'

export default function VehicleFormPage() {
    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Frota', to: '/app/cadastros/frota' },
                    { text: 'Veículos', to: listPath },
                    { text: 'Novo' },
                ]}
            />
            <div>
                Formulário de cadastro do veículo. Aqui entram placa, proprietário, responsável e os demais dados da frota.
            </div>
        </>
    )
}
