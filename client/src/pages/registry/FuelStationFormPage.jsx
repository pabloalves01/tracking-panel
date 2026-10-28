import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router'

import { InputCpfCnpj } from '@/components/common/inputs/InputCpfCnpj'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import { lookupFuelStation, requestFuelSupplyLink } from '@/lib/registry/fuelStations'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/postos'

const stationStatusLabel = {
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

export default function FuelStationFormPage() {
    const navigate = useNavigate()
    const [loading, setLoading] = useState(false)
    const [cnpj, setCnpj] = useState('')
    const [cnpjError, setCnpjError] = useState('')
    const [station, setStation] = useState(null)

    useEffect(() => {
        if (cnpj.length !== 14) {
            setStation(null)
            setCnpjError('')
            return
        }

        let cancelled = false
        setStation(null)
        setCnpjError('')
        setLoading(true)

        lookupFuelStation(cnpj)
            .then((data) => {
                if (cancelled) {
                    return
                }

                setStation(data)
                setCnpjError('')
            })
            .catch((error) => {
                if (cancelled) {
                    return
                }

                setStation(null)
                const message = fieldError(error, 'document')

                if (message) {
                    setCnpjError(message)
                } else {
                    notifyError(error)
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false)
                }
            })

        return () => {
            cancelled = true
        }
    }, [cnpj])

    async function handleSubmit(event) {
        event.preventDefault()

        if (!station) {
            return
        }

        setLoading(true)

        try {
            const response = await requestFuelSupplyLink(cnpj)
            notifySuccess(response)
            navigate(listPath)
        } catch (error) {
            const message = fieldError(error, 'document')

            if (message) {
                setCnpjError(message)
                setStation(null)
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
                        disabled: !station || Boolean(cnpjError),
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
                                    onChange={setCnpj}
                                    aria-invalid={cnpjError ? true : undefined}
                                    aria-describedby={cnpjError ? 'posto-cnpj-error' : undefined}
                                    required
                                />
                                <FieldError id="posto-cnpj-error">{cnpjError}</FieldError>
                            </Field>
                            {station ? (
                                <>
                                    <Field className="col-span-12 md:col-span-4">
                                        <FieldLabel htmlFor="posto-status">Situação</FieldLabel>
                                        <Input
                                            id="posto-status"
                                            value={stationStatusLabel[station.status] ?? station.status ?? ''}
                                            disabled
                                        />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-4">
                                        <FieldLabel htmlFor="posto-corporate-name">Razão social</FieldLabel>
                                        <Input id="posto-corporate-name" value={station.legal_name ?? ''} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-4">
                                        <FieldLabel htmlFor="posto-trade-name">Nome fantasia</FieldLabel>
                                        <Input id="posto-trade-name" value={station.trade_name ?? ''} disabled />
                                    </Field>
                                </>
                            ) : null}
                        </FieldGroup>
                    </FieldSet>

                    {station ? (
                        <>
                            <Separator />
                            <FieldSet>
                                <FieldLegend variant="label">Endereço</FieldLegend>
                                <FieldDescription>Endereço cadastrado do posto.</FieldDescription>
                                <FieldGroup className="md:grid grid-cols-1 md:grid-cols-12">
                                    <Field className="col-span-12 md:col-span-3">
                                        <FieldLabel htmlFor="posto-zip">CEP</FieldLabel>
                                        <Input id="posto-zip" value={formatZipCode(station.zip_code)} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-6">
                                        <FieldLabel htmlFor="posto-street">Rua</FieldLabel>
                                        <Input id="posto-street" value={station.street ?? ''} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-3">
                                        <FieldLabel htmlFor="posto-number">Número</FieldLabel>
                                        <Input id="posto-number" value={station.number ?? ''} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-6">
                                        <FieldLabel htmlFor="posto-complement">Complemento</FieldLabel>
                                        <Input id="posto-complement" value={station.complement ?? ''} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-6">
                                        <FieldLabel htmlFor="posto-district">Bairro</FieldLabel>
                                        <Input id="posto-district" value={station.district ?? ''} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-6">
                                        <FieldLabel htmlFor="posto-uf">Estado</FieldLabel>
                                        <Input id="posto-uf" value={station.uf ?? ''} disabled />
                                    </Field>
                                    <Field className="col-span-12 md:col-span-6">
                                        <FieldLabel htmlFor="posto-city">Cidade</FieldLabel>
                                        <Input id="posto-city" value={station.city_name ?? ''} disabled />
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
