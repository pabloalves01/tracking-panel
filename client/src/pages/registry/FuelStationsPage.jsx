import { useEffect, useState } from 'react'
import { Fuel, Trash2Icon } from 'lucide-react'

import { ActionButton } from '@/components/common/button/ActionButton'
import { InputCpfCnpj } from '@/components/common/inputs/InputCpfCnpj'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatCpfCnpj } from '@/helpers/formatters'
import { deleteCompanyFuelStation, getCompanyFuelStations, storeCompanyFuelStation } from '@/lib/registry/fuelStations'
import { notifyError, notifySuccess } from '@/lib/notify'

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

const statusLabel = {
    pending: 'Pendente',
    accepted: 'Aceito',
    rejected: 'Recusado',
}

export default function FuelStationsPage() {
    const [links, setLinks] = useState([])
    const [loading, setLoading] = useState(false)
    const [cnpj, setCnpj] = useState('')
    const [cnpjError, setCnpjError] = useState('')

    useEffect(() => {
        loadLinks()
    }, [])

    function loadLinks() {
        setLoading(true)
        getCompanyFuelStations()
            .then(setLinks)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }

    function handleCnpjChange(value) {
        setCnpj(value)
        setCnpjError('')
    }

    async function handleSubmit(event) {
        event.preventDefault()
        setLoading(true)
        setCnpjError('')

        try {
            const response = await storeCompanyFuelStation(cnpj)
            notifySuccess(response)
            setCnpj('')
            loadLinks()
        } catch (error) {
            const message = fieldError(error, 'cnpj')

            if (message) {
                setCnpjError(message)
            } else {
                notifyError(error)
            }

            setLoading(false)
        }
    }

    async function handleRemove(link) {
        setLoading(true)

        try {
            const response = await deleteCompanyFuelStation(link.cnpj)
            notifySuccess(response)
            loadLinks()
        } catch (error) {
            notifyError(error)
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
                    { text: 'Postos' },
                ]}
            />

            <form onSubmit={handleSubmit} className="mb-6 max-w-md">
                <FieldGroup>
                    <Field data-invalid={cnpjError ? true : undefined}>
                        <FieldLabel htmlFor="posto-cnpj">CNPJ do posto</FieldLabel>
                        <InputCpfCnpj
                            id="posto-cnpj"
                            type="cnpj"
                            value={cnpj}
                            onChange={handleCnpjChange}
                            aria-invalid={cnpjError ? true : undefined}
                            aria-describedby={cnpjError ? 'posto-cnpj-error' : undefined}
                        />
                        <FieldError id="posto-cnpj-error">{cnpjError}</FieldError>
                    </Field>
                    <Button type="submit" disabled={loading || cnpj.length !== 14}>
                        Pedir vínculo
                    </Button>
                </FieldGroup>
            </form>

            <LoadingWrapper loading={loading}>
                {!loading && links.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Fuel />
                            </EmptyMedia>
                            <EmptyTitle>Nenhum posto</EmptyTitle>
                            <EmptyDescription>
                                Informe o CNPJ de um posto já cadastrado no Logiv. O posto aceita ou recusa o pedido.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Posto</TableHead>
                                <TableHead>CNPJ</TableHead>
                                <TableHead>Situação</TableHead>
                                <TableHead>Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {links.map((link) => (
                                <TableRow key={link.cnpj}>
                                    <TableCell>
                                        {link.fuel_station?.trade_name
                                            || link.fuel_station?.corporate_name}
                                    </TableCell>
                                    <TableCell>{formatCpfCnpj(link.cnpj)}</TableCell>
                                    <TableCell>
                                        <Badge variant={link.status === 'accepted' ? 'secondary' : 'outline'}>
                                            {statusLabel[link.status] ?? link.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <ActionButton
                                            icon={Trash2Icon}
                                            tooltip="Remover"
                                            variant="destructive"
                                            onClick={() => handleRemove(link)}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </LoadingWrapper>
        </>
    )
}
