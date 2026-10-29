import { LayoutToolbar } from "@/components/common/LayoutToolbar"
import { Badge } from "@/components/ui/badge"
import { formatCpfCnpj } from "@/helpers/formatters"
import { useSession } from "@/lib/auth/session"

export default function FuelStationHome() {
    const { user, current_organization: fuelStation } = useSession()
    const firstName = user?.name?.split(" ")[0]
    const fuelStationName = fuelStation?.display_name

    return (
        <>
            <LayoutToolbar breadcrumbs={[{ text: "Início" }]} />

            <header className="mb-6">
                <h1 className="text-3xl font-semibold tracking-tight">
                    Bem-vindo, {firstName}!
                </h1>
                <p className="mt-1 text-muted-foreground">Resumo de {fuelStationName}.</p>
            </header>

            <section className="rounded-xl border bg-card">
                <div className="flex items-center justify-between gap-3 border-b px-5 py-4">
                    <h2 className="font-medium">{fuelStationName}</h2>
                    <Badge variant={fuelStation.status === "active" ? "secondary" : "outline"}>
                        {fuelStation.status === "active" ? "Ativo" : "Inativo"}
                    </Badge>
                </div>
                <dl className="grid gap-4 px-5 py-4 sm:grid-cols-2">
                    <Item label="Razão social" value={fuelStation?.legal_name} />
                    <Item label="Nome fantasia" value={fuelStation?.trade_name} />
                    <Item label="CNPJ" value={formatCpfCnpj(fuelStation?.document)} />
                    <Item label="Endereço" value={addressOf(fuelStation)} className="sm:col-span-2" />
                </dl>
            </section>
        </>
    )
}

function Item({ label, value, className }) {
    return (
        <div className={`flex flex-col gap-1 ${className ?? ""}`}>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">{value || "—"}</dd>
        </div>
    )
}

function addressOf(fuelStation) {
    const street = [fuelStation.street, fuelStation.street_number].filter(Boolean).join(", ")
    const place = [fuelStation.neighborhood, fuelStation.city, fuelStation.state].filter(Boolean).join(" · ")

    return [street, fuelStation.complement, place, formatZip(fuelStation.postal_code)].filter(Boolean).join(" · ")
}

function formatZip(zip) {
    const digits = String(zip ?? "").replace(/\D/g, "")
    if (digits.length !== 8) return digits
    return `${digits.slice(0, 5)}-${digits.slice(5)}`
}
