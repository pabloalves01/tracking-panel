import { Children, useEffect, useState } from "react"
import { Building2, Fuel } from "lucide-react"
import { Link, useNavigate } from "react-router"

import { Button } from "@/components/ui/button"
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty"
import { roleLabel } from "@/helpers/labels/roleLabel"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { formatCpfCnpj } from "@/helpers/formatters"
import { getAuthContexts, logout, me, selectCompany, selectFuelStation } from "@/lib/auth/auth"
import { notifyError } from "@/lib/notify"

export default function SelectContextPage() {
    const navigate = useNavigate()
    const [user, setUser] = useState(null)
    const [companies, setCompanies] = useState([])
    const [stations, setStations] = useState([])
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        Promise.all([me(), getAuthContexts()])
            .then(([currentUser, contexts]) => {
                setUser(currentUser)
                setCompanies(contexts.companies ?? [])
                setStations(contexts.fuel_stations ?? [])
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [])

    async function handleCompanyClick(uuid) {
        setLoading(true)

        try {
            await selectCompany(uuid)
            navigate("/app")
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    async function handleStationClick(uuid) {
        setLoading(true)

        try {
            await selectFuelStation(uuid)
            navigate("/posto")
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    async function handleLogout() {
        setLoading(true)

        try {
            await logout()
            navigate("/auth/login", { replace: true })
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    return (
        <LoadingWrapper loading={loading}>
            <div className="relative flex min-h-svh flex-col overflow-hidden">
                <WaveBackground />

                <div className="relative z-10 flex flex-1 items-center justify-center px-6 py-16">
                    <div className="flex w-full max-w-[76rem] flex-col items-center gap-8">
                        <div className="flex flex-col gap-1 text-center">
                            <p className="text-sm text-muted-foreground">Bem-vindo, {user?.name}</p>
                            <h1 className="text-2xl font-bold">Selecionar o contexto</h1>
                            <p className="text-sm text-muted-foreground">
                                Escolha em qual empresa ou posto você quer entrar.
                            </p>
                        </div>

                        {!loading && companies.length === 0 && stations.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Building2 />
                                    </EmptyMedia>
                                    <EmptyTitle>Nenhum contexto</EmptyTitle>
                                    <EmptyDescription>
                                        O acesso a uma empresa chega por convite. Quem será proprietário de um posto pode cadastrá-lo agora.
                                    </EmptyDescription>
                                </EmptyHeader>
                                <EmptyContent>
                                    <Button render={<Link to="/auth/register/posto" />}>Cadastrar posto</Button>
                                </EmptyContent>
                            </Empty>
                        ) : !loading ? (
                            <div className="flex w-full flex-col items-center gap-10">
                                <ContextSection title="Empresas" empty="Nenhuma empresa vinculada.">
                                    {companies.map((company) => (
                                        <ContextCard
                                            key={company.uuid}
                                            icon={Building2}
                                            title={company.trade_name}
                                            subtitle={company.legal_name}
                                            document={company.cnpj}
                                            role={roleLabel[company.role]}
                                            onClick={() => handleCompanyClick(company.uuid)}
                                        />
                                    ))}
                                </ContextSection>
                                <ContextSection title="Postos" empty="Nenhum posto vinculado.">
                                    {stations.map((station) => (
                                        <ContextCard
                                            key={station.uuid}
                                            icon={Fuel}
                                            title={station.trade_name || station.corporate_name}
                                            subtitle={station.trade_name ? station.corporate_name : ""}
                                            document={station.cnpj}
                                            role={roleLabel[station.role]}
                                            onClick={() => handleStationClick(station.uuid)}
                                        />
                                    ))}
                                </ContextSection>
                            </div>
                        ) : null}

                        <Button variant="outline" onClick={handleLogout} className="cursor-pointer">
                            Sair
                        </Button>
                    </div>
                </div>

                <footer className="relative z-10 flex flex-col items-center gap-1 px-6 pb-6 text-center text-xs text-muted-foreground">
                    <p>VF3M TECNOLOGIA LTDA · CNPJ 63.005.739/0001-06 © Todos os direitos reservados.</p>
                </footer>
            </div>
        </LoadingWrapper>
    )
}

function ContextSection({ title, empty, children }) {
    return (
        <section className="flex w-full flex-col items-center gap-4">
            <h2 className="text-lg font-semibold">{title}</h2>
            {Children.count(children) === 0 ? (
                <p className="text-sm text-muted-foreground">{empty}</p>
            ) : (
                <div className="flex w-full flex-wrap justify-center gap-4">{children}</div>
            )}
        </section>
    )
}

function ContextCard({ icon: Icon, title, subtitle, document, role, onClick }) {
    return (
        <button
            type="button"
            className="flex w-full max-w-sm flex-col gap-4 rounded-xl border bg-card/95 p-5 text-left text-card-foreground shadow-sm backdrop-blur-sm transition-colors hover:bg-muted sm:w-72 cursor-pointer"
            onClick={onClick}
        >
            <span className="flex size-10 items-center justify-center rounded-lg bg-muted">
                <Icon className="size-5" />
            </span>
            <span className="flex flex-col gap-1">
                <span className="font-medium">{title}</span>
                {subtitle ? <span className="text-sm text-muted-foreground">{subtitle}</span> : null}
                {role ? <span className="text-sm text-muted-foreground">{role}</span> : null}
                <span className="text-sm text-muted-foreground">{formatCpfCnpj(document)}</span>
            </span>
        </button>
    )
}

function WaveBackground() {
    return (
        <div className="pointer-events-none absolute inset-x-0 top-[68%] bottom-0 overflow-hidden" aria-hidden="true">
            <style>
                {`
                    @keyframes auth-wave {
                        from { transform: translateX(0); }
                        to { transform: translateX(-50%); }
                    }
                    @media (prefers-reduced-motion: reduce) {
                        .auth-wave { animation: none !important; }
                    }
                `}
            </style>
            <div className="auth-wave absolute inset-0 flex w-[200%] text-primary/15" style={{ animation: "auth-wave 18s linear infinite" }}>
                <Wave />
                <Wave />
            </div>
            <div className="auth-wave absolute inset-0 flex w-[200%] text-primary/10" style={{ animation: "auth-wave 28s linear infinite reverse" }}>
                <Wave offset />
                <Wave offset />
            </div>
        </div>
    )
}

function Wave({ offset = false }) {
    const path = offset
        ? "M0,70 C240,10 480,130 720,70 C960,10 1200,130 1440,70 L1440,320 L0,320 Z"
        : "M0,40 C240,110 480,0 720,40 C960,110 1200,0 1440,40 L1440,320 L0,320 Z"

    return (
        <svg className="h-full w-1/2 shrink-0" viewBox="0 0 1440 320" preserveAspectRatio="none">
            <path d={path} fill="currentColor" />
        </svg>
    )
}
