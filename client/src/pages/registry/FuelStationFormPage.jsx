import { useRef, useState } from 'react'
import { Copy } from 'lucide-react'
import { useNavigate } from 'react-router'

import { InputCpfCnpj } from '@/components/common/inputs/InputCpfCnpj'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import { lookupFuelStation, requestFuelStationAgreement } from '@/lib/registry/fuelStations'
import { copyText, notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/postos'

const fuelStationStatusLabel = {
    active: 'Ativo',
    inactive: 'Inativo',
}

function fieldError(error, field) {
    const data = error?.response?.data
    const message = data?.errors?.[field]?.find((item) => typeof item === 'string' && item.trim())

    if (message) {
        return message
    }

    if (error?.response?.status === 422 && typeof data?.message === 'string' && data.message.trim()) {
        return data.message
    }

    return ''
}

function formatZipCode(value) {
    const digits = String(value ?? '').replace(/\D/g, '')

    if (digits.length !== 8) {
        return value ?? ''
    }

    return `${digits.slice(0, 5)}-${digits.slice(5)}`
}

/** O cadastro de posto aceita o CNPJ na URL e já abre preenchido. */
function signupLink(cnpj) {
    return `${window.location.origin}/auth/register/posto?cnpj=${cnpj}`
}

export default function FuelStationFormPage() {
    const navigate = useNavigate()
    const [loading, setLoading] = useState(false)
    const [cnpj, setCnpj] = useState('')
    const [cnpjError, setCnpjError] = useState('')
    const [fuelStation, setFuelStation] = useState(null)
    // A busca responde 200 com `data: null` quando o CNPJ não tem posto. Sem
    // este estado a tela não distinguia "ainda não buscou" de "buscou e não
    // achou", e ficava parada sem dizer nada.
    const [notFound, setNotFound] = useState(false)

    // A busca sai da digitação, não de um efeito sobre o CNPJ: assim a tela
    // não renderiza em cascata, e o contador descarta a resposta de uma
    // busca que já foi superada por outra.
    const lookupRun = useRef(0)

    async function handleCnpjChange(value) {
        setCnpj(value)
        setFuelStation(null)
        setNotFound(false)
        setCnpjError('')

        const run = ++lookupRun.current

        if (value.length !== 14) {
            setLoading(false)
            return
        }

        setLoading(true)

        try {
            const data = await lookupFuelStation(value)
            if (run !== lookupRun.current) return

            setFuelStation(data)
            setNotFound(data === null)
        } catch (error) {
            if (run !== lookupRun.current) return

            const message = fieldError(error, 'document')

            if (message) {
                setCnpjError(message)
            } else {
                notifyError(error)
            }
        } finally {
            if (run === lookupRun.current) setLoading(false)
        }
    }

    async function handleSubmit(event) {
        event.preventDefault()

        if (!fuelStation) {
            return
        }

        setLoading(true)

        try {
            const response = await requestFuelStationAgreement(cnpj)
            notifySuccess(response)
            navigate(listPath)
        } catch (error) {
            const message = fieldError(error, 'document')

            if (message) {
                setCnpjError(message)
                setFuelStation(null)
            } else {
                notifyError(error)
            }
        } finally {
            setLoading(false)
        }
    }

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Entidades', to: '/app/cadastros/entidades' },
                    { text: 'Postos', to: listPath },
                    { text: 'Novo posto' },
                ]}
                buttons={[
                    { title: 'Cancelar', variant: 'outline', to: listPath },
                    {
                        title: 'Pedir vínculo',
                        permission: 'write',
                        disabled: !fuelStation || Boolean(cnpjError),
                        onClick: () => document.getElementById('fuel-station-form')?.requestSubmit(),
                    },
                ]}
            />

            <LoadingWrapper loading={loading}>
                <form id="fuel-station-form" className="flex w-full flex-col gap-10" onSubmit={handleSubmit}>
                    <FieldSet>
                        <FieldLegend variant="label">Dados do posto</FieldLegend>
                        <FieldDescription>
                            Informe o CNPJ de um posto já cadastrado no Logiv. Os dados aparecem para conferência antes do pedido.
                        </FieldDescription>
                        <FieldGroup className="md:grid grid-cols-1 md:grid-cols-12">
                            <Field className="col-span-12 md:col-span-12" data-invalid={cnpjError ? true : undefined}>
                                <FieldLabel htmlFor="posto-cnpj">CNPJ</FieldLabel>
                                <InputCpfCnpj
                                    id="posto-cnpj"
                                    type="cnpj"
                                    value={cnpj}
                                    onChange={handleCnpjChange}
                                    aria-invalid={cnpjError ? true : undefined}
                                    aria-describedby={cnpjError ? 'posto-cnpj-error' : undefined}
                                    required
                                />
                                <FieldError id="posto-cnpj-error">{cnpjError}</FieldError>
                            </Field>
                            {notFound ? (
                                <Alert className="col-span-12">
                                    <AlertTitle>Este posto ainda não está no LogIV</AlertTitle>
                                    <AlertDescription>
                                        O vínculo só pode ser pedido a um posto cadastrado. Envie o link de cadastro ao
                                        posto — o CNPJ já vai preenchido. Depois que ele se cadastrar, volte aqui e peça
                                        o vínculo.
                                    </AlertDescription>
                                    <AlertAction>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => copyText(signupLink(cnpj), 'Link de cadastro copiado')}
                                        >
                                            <Copy />
                                            Copiar link
                                        </Button>
                                    </AlertAction>
                                </Alert>
                            ) : null}
                            {fuelStation ? (
                                <>
                                    <Field className="col-span-12 md:col-span-4">
                                        <FieldLabel htmlFor="posto-status">Situação</FieldLabel>
                                        <Input
                                            id="posto-status"
                                            value={fuelStationStatusLabel[fuelStation.status] ?? fuelStation.status ?? ''}
                                            disabled
                                        />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-4">
                                        <FieldLabel htmlFor="posto-corporate-name">Razão social</FieldLabel>
                                        <Input id="posto-corporate-name" value={fuelStation.legal_name ?? ''} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-4">
                                        <FieldLabel htmlFor="posto-trade-name">Nome fantasia</FieldLabel>
                                        <Input id="posto-trade-name" value={fuelStation.trade_name ?? ''} disabled />
                                    </Field>
                                </>
                            ) : null}
                        </FieldGroup>
                    </FieldSet>

                    {fuelStation ? (
                        <>
                            <Separator />
                            <FieldSet>
                                <FieldLegend variant="label">Endereço</FieldLegend>
                                <FieldDescription>Endereço cadastrado do posto.</FieldDescription>
                                <FieldGroup className="md:grid grid-cols-1 md:grid-cols-12">
                                    <Field className="col-span-12 md:col-span-3">
                                        <FieldLabel htmlFor="posto-zip">CEP</FieldLabel>
                                        <Input id="posto-zip" value={formatZipCode(fuelStation.postal_code)} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-6">
                                        <FieldLabel htmlFor="posto-street">Rua</FieldLabel>
                                        <Input id="posto-street" value={fuelStation.street ?? ''} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-3">
                                        <FieldLabel htmlFor="posto-number">Número</FieldLabel>
                                        <Input id="posto-number" value={fuelStation.street_number ?? ''} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-6">
                                        <FieldLabel htmlFor="posto-complement">Complemento</FieldLabel>
                                        <Input id="posto-complement" value={fuelStation.complement ?? ''} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-6">
                                        <FieldLabel htmlFor="posto-district">Bairro</FieldLabel>
                                        <Input id="posto-district" value={fuelStation.neighborhood ?? ''} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-6">
                                        <FieldLabel htmlFor="posto-uf">Estado</FieldLabel>
                                        <Input id="posto-uf" value={fuelStation.state ?? ''} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-6">
                                        <FieldLabel htmlFor="posto-city">Cidade</FieldLabel>
                                        <Input id="posto-city" value={fuelStation.city ?? ''} disabled />
                                    </Field>
                                </FieldGroup>
                            </FieldSet>
                        </>
                    ) : null}
                </form>
            </LoadingWrapper>
        </>
    )
}
