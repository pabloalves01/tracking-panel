import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { InfoIcon, TriangleAlertIcon } from 'lucide-react'

import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { TabButton } from '@/components/common/TabButton'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { InputCity } from '@/components/common/inputs/InputCity'
import { InputCpfCnpj } from '@/components/common/inputs/InputCpfCnpj'
import { InputEmergencyNumber } from '@/components/common/inputs/InputEmergencyNumber'
import { InputPhone } from '@/components/common/inputs/InputPhone'
import { InputPostalCode } from '@/components/common/inputs/InputPostalCode'
import { SelectState } from '@/components/common/inputs/SelectState'
import { SelectUf } from '@/components/common/inputs/SelectUf'
import { DatePicker } from '@/components/datepicker/DatePicker'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { Spinner } from '@/components/ui/spinner'
import {
    createEntity,
    getEntity,
    getEntityByDocument,
    updateEntity,
} from '@/lib/registry/entities'
import { ANTT_CATEGORIES } from '@/lib/fleet/options'
import { ENTITY_ROLES, rolePath, roleFromSlug } from '@/lib/registry/roles'
import { notifyError, notifySuccess } from '@/lib/notify'

const kinds = [
    { value: 'organization', label: 'Pessoa jurídica' },
    { value: 'individual', label: 'Pessoa física' },
]

const pixKeyTypes = [
    { value: 'cpf', label: 'CPF' },
    { value: 'cnpj', label: 'CNPJ' },
    { value: 'email', label: 'E-mail' },
    { value: 'phone', label: 'Telefone' },
    { value: 'random', label: 'Chave aleatória' },
]

/** O exemplo do campo da chave muda com o tipo escolhido. */
const pixPlaceholder = {
    cpf: '000.000.000-00',
    cnpj: '00.000.000/0000-00',
    email: 'nome@empresa.com.br',
    phone: '(00) 00000-0000',
    random: 'Cole a chave aleatória',
}

const licenseCategories = ['A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE', 'ACC']

/** Em que aba mora cada campo — é para lá que a tela vai quando ele dá erro. */
function tabOf(field) {
    if (field.startsWith('license_')) return 'cnh'
    if (field.startsWith('rntrc') || field === 'antt_category') return 'antt'
    if (field.startsWith('pix_')) return 'pagamento'

    return 'geral'
}

const emptyForm = {
    kind: 'organization',
    document: '',
    name: '',
    trade_name: '',
    email: '',
    phone: '',
    emergency_phone: '',
    emergency_phone_type: 'mobile',
    national_id: '',
    state_registration: '',
    pix_key_type: '',
    pix_key: '',
    postal_code: '',
    street: '',
    street_number: '',
    complement: '',
    neighborhood: '',
    state_id: null,
    city_id: null,
    license_number: '',
    license_category: '',
    license_expires_on: null,
    license_issued_state: '',
    rntrc: '',
    antt_category: '',
    rntrc_expires_on: null,
}

/**
 * O formulário a partir da pessoa e, na edição, do registro do papel — que
 * traz as colunas próprias (a CNH do motorista) no mesmo nível.
 */
function formFrom(entity, record = {}) {
    const source = { ...entity, ...record }

    return {
        ...emptyForm,
        ...Object.fromEntries(
            Object.keys(emptyForm)
                .filter((key) => source[key] !== undefined && source[key] !== null)
                .map((key) => [key, source[key]])
        ),
    }
}

/**
 * O formulário de entidade, um só para os sete cadastros.
 *
 * A diferença de uso em relação à versão anterior é a ordem: o documento vem
 * primeiro e é consultado enquanto se digita. Antes o formulário começava
 * pelo nome e só descobria que a pessoa já existia na hora de salvar — e aí
 * descartava em silêncio o que tinha sido digitado.
 */
export default function EntityFormPage() {
    const { role: slug, uuid } = useParams()
    const navigate = useNavigate()
    const [searchParams, setSearchParams] = useSearchParams()

    const config = roleFromSlug(slug) ?? ENTITY_ROLES.customer
    const basePath = rolePath(config)
    const isEditing = Boolean(uuid)

    const [form, setForm] = useState(emptyForm)
    const [loading, setLoading] = useState(isEditing)
    const [saving, setSaving] = useState(false)
    const [roles, setRoles] = useState([])

    /** O que a consulta por documento encontrou: null, 'free' ou 'taken'. */
    const [match, setMatch] = useState(null)
    const [lookingUp, setLookingUp] = useState(false)

    /** Abas com campo recusado pela API no último envio. */
    const [errorTabs, setErrorTabs] = useState([])

    const isCompany = form.kind === 'organization'
    const expectedLength = isCompany ? 14 : 11

    // A aba vai na URL, como na tela de usuários: recarregar ou voltar cai
    // no mesmo lugar.
    const tabs = [
        { key: 'geral', label: 'Dados gerais' },
        ...(config.hasDetails ? [{ key: 'cnh', label: 'CNH' }] : []),
        ...(config.hasAntt ? [{ key: 'antt', label: 'ANTT' }] : []),
        { key: 'pagamento', label: 'Pagamento' },
    ]
    const requested = searchParams.get('aba')
    const tab = tabs.some((item) => item.key === requested) ? requested : 'geral'

    function selectTab(next) {
        const params = new URLSearchParams(searchParams)

        if (next === 'geral') params.delete('aba')
        else params.set('aba', next)

        setSearchParams(params, { replace: true })
    }

    useEffect(() => {
        // `loading` já nasce verdadeiro na edição.
        if (!isEditing) return

        getEntity(config.role, uuid)
            .then((record) => {
                setForm(formFrom(record.entity, record))
                setRoles(record.entity.roles ?? [])
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [config.role, uuid, isEditing])

    function set(field, value) {
        setForm((current) => ({ ...current, [field]: value }))
    }

    /**
     * Consulta o documento assim que ele fica completo. Três desfechos:
     * ninguém, alguém sem este papel (carrega os dados) ou alguém que já
     * exerce o papel (manda para a edição em vez de duplicar).
     */
    async function lookup(document) {
        if (isEditing || document.length !== expectedLength) {
            setMatch(null)
            return
        }

        setLookingUp(true)

        try {
            const entity = await getEntityByDocument(document)

            if (!entity) {
                setMatch(null)
                setRoles([])
                return
            }

            const current = (entity.roles ?? []).find((item) => item.role === config.role)

            setForm({ ...formFrom(entity), document })
            setRoles(entity.roles ?? [])
            setMatch(current ? { kind: 'taken', entity, uuid: current.uuid } : { kind: 'free', entity })
        } catch (error) {
            notifyError(error)
        } finally {
            setLookingUp(false)
        }
    }

    async function handleSubmit(event) {
        event.preventDefault()
        if (match?.kind === 'taken') return

        setSaving(true)
        setErrorTabs([])

        const payload = {
            ...form,
            pix_key_type: form.pix_key_type || null,
            pix_key: form.pix_key || null,
            state_id: form.state_id || null,
            city_id: form.city_id || null,
            postal_code: form.postal_code || null,
        }

        if (!config.hasDetails) {
            delete payload.license_number
            delete payload.license_category
            delete payload.license_expires_on
            delete payload.license_issued_state
        }

        if (config.hasAntt) {
            payload.rntrc = form.rntrc || null
            payload.antt_category = form.antt_category || null
        } else {
            delete payload.rntrc
            delete payload.antt_category
            delete payload.rntrc_expires_on
        }

        try {
            const response = isEditing
                ? await updateEntity(config.role, uuid, payload)
                : await createEntity(config.role, payload)

            notifySuccess(response)
            navigate(basePath)
        } catch (error) {
            notifyError(error)

            // O erro pode ser de um campo que está em outra aba. Sem isto a
            // pessoa via o aviso e não achava o campo: a tela vai para a aba
            // do primeiro erro, e as outras com erro ganham a bolinha.
            const fields = Object.keys(error?.response?.data?.errors ?? {})

            if (fields.length > 0) {
                const withErrors = [...new Set(fields.map(tabOf))]
                setErrorTabs(withErrors)
                selectTab(tabOf(fields[0]))
            }
        } finally {
            setSaving(false)
        }
    }

    const otherRoles = roles.filter((item) => item.role !== config.role)
    const title = isEditing
        ? `Editar ${config.label.toLowerCase()}`
        : `${config.feminine ? 'Nova' : 'Novo'} ${config.label.toLowerCase()}`

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Entidades', to: '/app/cadastros/entidades' },
                    { text: config.plural, to: basePath },
                    { text: title },
                ]}
                buttons={[
                    { title: 'Cancelar', variant: 'ghost', to: basePath },
                    {
                        title: saving ? 'Salvando…' : 'Salvar',
                        onClick: handleSubmit,
                        disabled: saving || match?.kind === 'taken',
                    },
                ]}
            />

            <LoadingWrapper loading={loading}>
                <form onSubmit={handleSubmit}>
                    {/* Aviso de quem já existe. É o que evita a redigitação e
                        o descarte silencioso de dados da versão anterior. */}
                    {match?.kind === 'free' ? (
                        <Alert className="mb-5">
                            <InfoIcon />
                            <AlertTitle>{match.entity.name} já está no cadastro</AlertTitle>
                            <AlertDescription>
                                {otherRoles.length > 0 ? (
                                    <>
                                        Hoje {config.article === 'a' ? 'ela' : 'ele'} é{' '}
                                        {otherRoles.map((item) => item.label.toLowerCase()).join(' e ')}.
                                        Os dados abaixo são {config.article === 'a' ? 'os dela' : 'os dele'} —
                                        revise e confirme para adicionar o papel de{' '}
                                        {config.label.toLowerCase()}.
                                    </>
                                ) : (
                                    <>
                                        Os dados abaixo são os que já estão gravados. Revise e
                                        confirme para adicionar o papel de {config.label.toLowerCase()}.
                                    </>
                                )}
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {match?.kind === 'taken' ? (
                        <Alert variant="destructive" className="mb-5">
                            <TriangleAlertIcon />
                            <AlertTitle>
                                {match.entity.name} já {config.article === 'a' ? 'é uma' : 'é um'}{' '}
                                {config.label.toLowerCase()}
                            </AlertTitle>
                            <AlertDescription className="flex flex-col items-start gap-2">
                                <span>
                                    Não dá para cadastrar duas vezes com o mesmo documento.
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    nativeButton={false}
                                    render={<Link to={`${basePath}/${match.uuid}/editar`} />}
                                >
                                    Abrir cadastro
                                </Button>
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {isEditing && otherRoles.length > 0 ? (
                        <div className="mb-5 flex flex-wrap items-center gap-2">
                            <span className="text-muted-foreground text-xs">Também é:</span>
                            {otherRoles.map((item) => (
                                <Badge
                                    key={item.role}
                                    variant="secondary"
                                    render={
                                        <Link
                                            to={`/app/cadastros/entidades/${item.slug}/${item.uuid}/editar`}
                                        />
                                    }
                                >
                                    {item.label}
                                </Badge>
                            ))}
                        </div>
                    ) : null}

                    <div role="tablist" className="mb-6 flex gap-1 border-b">
                        {tabs.map((item) => (
                            <TabButton
                                key={item.key}
                                active={tab === item.key}
                                alert={tab !== item.key && errorTabs.includes(item.key)}
                                onClick={() => selectTab(item.key)}
                            >
                                {item.label}
                            </TabButton>
                        ))}
                    </div>

                    {tab === 'geral' ? (
                        <>
                            <FieldSet>
                                <FieldLegend variant="label">Identificação</FieldLegend>
                            <FieldDescription>Quem é a pessoa ou a empresa. Comece pelo documento: se ela já estiver no cadastro, os dados aparecem para conferir.</FieldDescription>
                                <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                                    <Field className="col-span-12 md:col-span-4 xl:col-span-2">
                                        <FieldLabel htmlFor="kind">Tipo de pessoa</FieldLabel>
                                        <Select
                                            items={kinds}
                                            value={form.kind}
                                            onValueChange={(value) => {
                                                set('kind', value)
                                                set('document', '')
                                                setMatch(null)
                                            }}
                                            disabled={isEditing}
                                        >
                                            <SelectTrigger id="kind" className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {kinds.map((option) => (
                                                        <SelectItem key={option.value} value={option.value}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </Field>

                                    <Field className="col-span-12 md:col-span-4 xl:col-span-3">
                                        <FieldLabel htmlFor="document">
                                            {isCompany ? 'CNPJ' : 'CPF'}
                                            {lookingUp ? <Spinner className="size-3" aria-label="Procurando cadastro" /> : null}
                                        </FieldLabel>
                                        <InputCpfCnpj
                                            id="document"
                                            type={isCompany ? 'cnpj' : 'cpf'}
                                            value={form.document}
                                            onChange={(digits) => {
                                                set('document', digits)
                                                lookup(digits)
                                            }}
                                            disabled={isEditing}
                                        />
                                    </Field>

                                    <Field className={`col-span-12 md:col-span-4 ${isCompany ? 'xl:col-span-4' : 'xl:col-span-7'}`}>
                                        <FieldLabel htmlFor="name">
                                            {isCompany ? 'Razão social' : 'Nome'}
                                        </FieldLabel>
                                        <Input
                                            id="name"
                                            placeholder={isCompany ? 'Razão social, como no CNPJ' : 'Nome completo'}
                                            value={form.name}
                                            onChange={(event) => set('name', event.target.value)}
                                            required
                                        />
                                    </Field>

                                    {isCompany ? (
                                        <Field className="col-span-12 md:col-span-4 xl:col-span-3">
                                            <FieldLabel htmlFor="trade-name">Nome fantasia</FieldLabel>
                                            <Input
                                                id="trade-name"
                                                placeholder="Como a empresa é conhecida"
                                                value={form.trade_name}
                                                onChange={(event) => set('trade_name', event.target.value)}
                                            />
                                        </Field>
                                    ) : null}

                                    <Field className="col-span-12 md:col-span-4 xl:col-span-3">
                                        <FieldLabel htmlFor="email">E-mail</FieldLabel>
                                        <Input
                                            id="email"
                                            placeholder="contato@empresa.com.br"
                                            type="email"
                                            value={form.email}
                                            onChange={(event) => set('email', event.target.value)}
                                        />
                                    </Field>

                                    <Field className="col-span-12 md:col-span-4 xl:col-span-3">
                                        <FieldLabel htmlFor="phone">Telefone</FieldLabel>
                                        <InputPhone
                                            id="phone"
                                            value={form.phone}
                                            onChange={(digits) => set('phone', digits)}
                                        />
                                    </Field>

                                    <Field className="col-span-12 md:col-span-4 xl:col-span-2">
                                        <FieldLabel htmlFor="national-id">
                                            {isCompany ? 'Inscrição estadual' : 'RG'}
                                        </FieldLabel>
                                        <Input
                                            id="national-id"
                                            placeholder={isCompany ? 'Número ou ISENTO' : 'Número do RG'}
                                            value={isCompany ? form.state_registration : form.national_id}
                                            onChange={(event) =>
                                                set(
                                                    isCompany ? 'state_registration' : 'national_id',
                                                    event.target.value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field className="col-span-12 md:col-span-8 xl:col-span-4">
                                        <FieldLabel htmlFor="emergency-phone">
                                            Telefone de emergência
                                        </FieldLabel>
                                        <InputEmergencyNumber
                                            id="emergency-phone"
                                            value={form.emergency_phone}
                                            type={form.emergency_phone_type}
                                            onValueChange={(digits) => set('emergency_phone', digits)}
                                            onTypeChange={(type) => set('emergency_phone_type', type)}
                                        />
                                    </Field>
                                </FieldGroup>
                            </FieldSet>


                            <Separator className="my-6" />

                            <FieldSet>
                                <FieldLegend variant="label">Endereço</FieldLegend>
                            <FieldDescription>O endereço principal, usado em documentos e na operação.</FieldDescription>
                                <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                                    <Field className="col-span-12 md:col-span-3 xl:col-span-2">
                                        <FieldLabel htmlFor="postal-code">CEP</FieldLabel>
                                        <InputPostalCode
                                            id="postal-code"
                                            value={form.postal_code}
                                            onChange={(digits) => set('postal_code', digits)}
                                        />
                                    </Field>

                                    <Field className="col-span-12 md:col-span-7 xl:col-span-5">
                                        <FieldLabel htmlFor="street">Rua</FieldLabel>
                                        <Input
                                            id="street"
                                            placeholder="Rua, avenida, rodovia…"
                                            value={form.street}
                                            onChange={(event) => set('street', event.target.value)}
                                        />
                                    </Field>

                                    <Field className="col-span-12 md:col-span-2 xl:col-span-1">
                                        <FieldLabel htmlFor="street-number">Número</FieldLabel>
                                        <Input
                                            id="street-number"
                                            placeholder="Nº"
                                            value={form.street_number}
                                            onChange={(event) => set('street_number', event.target.value)}
                                        />
                                    </Field>

                                    <Field className="col-span-12 md:col-span-4 xl:col-span-4">
                                        <FieldLabel htmlFor="neighborhood">Bairro</FieldLabel>
                                        <Input
                                            id="neighborhood"
                                            placeholder="Bairro"
                                            value={form.neighborhood}
                                            onChange={(event) => set('neighborhood', event.target.value)}
                                        />
                                    </Field>

                                    <Field className="col-span-12 md:col-span-4 xl:col-span-4">
                                        <FieldLabel htmlFor="complement">Complemento</FieldLabel>
                                        <Input
                                            id="complement"
                                            placeholder="Sala, bloco, km…"
                                            value={form.complement}
                                            onChange={(event) => set('complement', event.target.value)}
                                        />
                                    </Field>

                                    <Field className="col-span-12 md:col-span-2 xl:col-span-3">
                                        <FieldLabel htmlFor="state">Estado</FieldLabel>
                                        <SelectState
                                            id="state"
                                            value={form.state_id}
                                            onChange={(value) => {
                                                set('state_id', value)
                                                set('city_id', null)
                                            }}
                                        />
                                    </Field>

                                    <Field className="col-span-12 md:col-span-2 xl:col-span-5">
                                        <FieldLabel htmlFor="city">Cidade</FieldLabel>
                                        <InputCity
                                            id="city"
                                            stateId={form.state_id}
                                            value={form.city_id}
                                            onChange={(value) => set('city_id', value)}
                                        />
                                    </Field>
                                </FieldGroup>
                            </FieldSet>
                        </>
                    ) : null}

                    {tab === 'cnh' && config.hasDetails ? (
                        <FieldSet>
                            <FieldLegend variant="label">CNH</FieldLegend>
                            <FieldDescription>A carteira de habilitação do motorista. Número e categoria são obrigatórios.</FieldDescription>
                            <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                                <Field className="col-span-12 md:col-span-4 xl:col-span-3">
                                    <FieldLabel htmlFor="license-number">Número</FieldLabel>
                                    <Input
                                        id="license-number"
                                        placeholder="Número do registro"
                                        value={form.license_number}
                                        onChange={(event) =>
                                            set('license_number', event.target.value)
                                        }
                                        required
                                    />
                                </Field>

                                <Field className="col-span-12 md:col-span-2 xl:col-span-2">
                                    <FieldLabel htmlFor="license-category">Categoria</FieldLabel>
                                    <Select
                                        items={licenseCategories.map((value) => ({
                                            value,
                                            label: value,
                                        }))}
                                        value={form.license_category}
                                        onValueChange={(value) => set('license_category', value)}
                                    >
                                        <SelectTrigger id="license-category" className="w-full">
                                            <SelectValue placeholder="Selecione" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {licenseCategories.map((value) => (
                                                    <SelectItem key={value} value={value}>
                                                        {value}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field className="col-span-12 md:col-span-3 xl:col-span-2">
                                    <FieldLabel htmlFor="license-expires">Validade</FieldLabel>
                                    <DatePicker
                                        id="license-expires"
                                        value={form.license_expires_on}
                                        onChange={(value) => set('license_expires_on', value)}
                                    />
                                </Field>

                                <Field className="col-span-12 md:col-span-3 xl:col-span-2">
                                    <FieldLabel htmlFor="license-state">UF de emissão</FieldLabel>
                                    <SelectUf
                                        id="license-state"
                                        value={form.license_issued_state}
                                        onChange={(value) => set('license_issued_state', value)}
                                    />
                                </Field>
                            </FieldGroup>
                        </FieldSet>
                    ) : null}

                    {tab === 'antt' && config.hasAntt ? (
                        <FieldSet>
                            <FieldLegend variant="label">ANTT</FieldLegend>
                            <FieldDescription>
                                O registro do transportador no RNTRC. Os veículos que rodam no RNTRC dele apontam para este
                                cadastro.
                            </FieldDescription>
                            <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                                <Field className="col-span-12 md:col-span-4 xl:col-span-3">
                                    <FieldLabel htmlFor="rntrc">RNTRC</FieldLabel>
                                    <Input
                                        id="rntrc"
                                        inputMode="numeric"
                                        placeholder="Número do registro"
                                        maxLength={12}
                                        value={form.rntrc}
                                        onChange={(event) => set('rntrc', event.target.value.replace(/\D/g, ''))}
                                    />
                                </Field>

                                <Field className="col-span-12 md:col-span-4 xl:col-span-3">
                                    <FieldLabel htmlFor="antt-category">Categoria</FieldLabel>
                                    <Select
                                        items={ANTT_CATEGORIES}
                                        value={form.antt_category || null}
                                        onValueChange={(value) => set('antt_category', value ?? '')}
                                    >
                                        <SelectTrigger id="antt-category" className="w-full">
                                            <SelectValue placeholder="Selecione" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {ANTT_CATEGORIES.map((option) => (
                                                    <SelectItem key={option.value} value={option.value}>
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field className="col-span-12 md:col-span-4 xl:col-span-2">
                                    <FieldLabel htmlFor="rntrc-expires">Validade</FieldLabel>
                                    <DatePicker
                                        id="rntrc-expires"
                                        value={form.rntrc_expires_on}
                                        onChange={(value) => set('rntrc_expires_on', value || null)}
                                    />
                                </Field>
                            </FieldGroup>
                        </FieldSet>
                    ) : null}

                    {tab === 'pagamento' ? (
                        <FieldSet>
                            <FieldLegend variant="label">Pagamento</FieldLegend>
                            <FieldDescription>
                                A chave PIX é da pessoa e vale para todos os cadastros dela.
                            </FieldDescription>
                            <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                                <Field className="col-span-12 md:col-span-4 xl:col-span-3">
                                    <FieldLabel htmlFor="pix-type">Tipo da chave</FieldLabel>
                                    <Select
                                        items={pixKeyTypes}
                                        value={form.pix_key_type}
                                        onValueChange={(value) => set('pix_key_type', value)}
                                    >
                                        <SelectTrigger id="pix-type" className="w-full">
                                            <SelectValue placeholder="Sem chave PIX" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {pixKeyTypes.map((option) => (
                                                    <SelectItem key={option.value} value={option.value}>
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field className="col-span-12 md:col-span-8 xl:col-span-6">
                                    <FieldLabel htmlFor="pix-key">Chave PIX</FieldLabel>
                                    <Input
                                        id="pix-key"
                                        placeholder={pixPlaceholder[form.pix_key_type] ?? 'Escolha o tipo da chave'}
                                        value={form.pix_key}
                                        onChange={(event) => set('pix_key', event.target.value)}
                                        disabled={!form.pix_key_type}
                                    />
                                </Field>
                            </FieldGroup>
                        </FieldSet>
                    ) : null}
                </form>
            </LoadingWrapper>
        </>
    )
}
