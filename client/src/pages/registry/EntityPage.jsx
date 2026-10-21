import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router'
import { ContactIcon, PencilIcon } from 'lucide-react'

import { ActionButton } from '@/components/common/button/ActionButton'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { Badge } from '@/components/ui/badge'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatCpfCnpj, formatPhone } from '@/helpers/formatters'
import { getEntities } from '@/lib/registry/entities'
import { notifyError } from '@/lib/notify'

const listPath = '/app/cadastros/entidades'

const personTypeLabel = {
    pf: 'Pessoa física',
    pj: 'Pessoa jurídica',
}

export default function EntityPage() {
    const navigate = useNavigate()
    const [entities, setEntities] = useState([])
    const [loading, setLoading] = useState(false)

    useEffect(() => {
        setLoading(true)
        getEntities()
            .then(setEntities)
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [])

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Entidades' },
                ]}
            />

            <LoadingWrapper loading={loading}>
                {!loading && entities.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ContactIcon />
                            </EmptyMedia>
                            <EmptyTitle>Nenhuma entidade</EmptyTitle>
                            <EmptyDescription>
                                As pessoas aparecem aqui quando são cadastradas como fornecedor, cliente ou outro papel.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nome</TableHead>
                                <TableHead>Nome fantasia</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Documento</TableHead>
                                <TableHead>E-mail</TableHead>
                                <TableHead>Telefone</TableHead>
                                <TableHead>Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {entities.map((entity) => (
                                <TableRow key={entity.uuid}>
                                    <TableCell>{entity.name}</TableCell>
                                    <TableCell>{entity.trade_name}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {personTypeLabel[entity.person_type] ?? entity.person_type}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{formatCpfCnpj(entity.cpf_cnpj)}</TableCell>
                                    <TableCell>{entity.email}</TableCell>
                                    <TableCell>{formatPhone(entity.phone)}</TableCell>
                                    <TableCell>
                                        <ActionButton
                                            icon={PencilIcon}
                                            tooltip="Editar"
                                            onClick={() => navigate(`${listPath}/${entity.uuid}/editar`)}
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
