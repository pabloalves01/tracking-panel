import { LayoutToolbar } from "@/components/common/LayoutToolbar"
import { Badge } from "@/components/ui/badge"
import { formatCpfCnpj } from "@/helpers/formatters"
import { useSession } from "@/lib/auth/session"
import { useStationSession } from "@/lib/auth/stationSession"

export default function StationHome() {
    const { user } = useSession()
    const { station } = useStationSession()
    const firstName = user?.name?.split(" ")[0]
    const stationName = station.trade_name || station.corporate_name

    return (
        <>
            <LayoutToolbar breadcrumbs={[{ text: "Início" }]} />

            <header className="mb-6">
                <h1 className="text-3xl font-semibold tracking-tight">
                    Bem-vindo, {firstName}!
                </h1>
                <p className="mt-1 text-muted-foreground">Resumo de {stationName}.</p>
            </header>

            <section className="rounded-xl border bg-card">
                <div className="flex items-center justify-between gap-3 border-b px-5 py-4">
                    <h2 className="font-medium">{stationName}</h2>
                    <Badge variant={station.status === "active" ? "secondary" : "outline"}>
                        {station.status === "active" ? "Ativo" : "Inativo"}
                    </Badge>
                </div>
                <dl className="grid gap-4 px-5 py-4 sm:grid-cols-2">
                    <Item label="Razão social" value={station.corporate_name} />
                    <Item label="Nome fantasia" value={station.trade_name} />
                    <Item label="CNPJ" value={formatCpfCnpj(station.cnpj)} />
                    <Item label="Endereço" value={addressOf(station)} className="sm:col-span-2" />
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

function addressOf(station) {
    const street = [station.street, station.number].filter(Boolean).join(", ")
    const place = [station.district, station.city_name, station.uf].filter(Boolean).join(" · ")

    return [street, station.complement, place, formatZip(station.zip_code)].filter(Boolean).join(" · ")
}

function formatZip(zip) {
    const digits = String(zip ?? "").replace(/\D/g, "")
    if (digits.length !== 8) return digits
    return `${digits.slice(0, 5)}-${digits.slice(5)}`
}
