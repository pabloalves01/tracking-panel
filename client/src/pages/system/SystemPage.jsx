import { LayoutToolbar } from "@/components/common/LayoutToolbar";

export default function SystemPage() {
    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Sistema' },
                ]}
            />

            <div>tela em desenvolvimento</div>
        </>
    )
}