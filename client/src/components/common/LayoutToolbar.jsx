import { Fragment } from 'react'
import { Link } from 'react-router'

import { Button } from '@/components/ui/button'
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'

/**
 * Barra da página: breadcrumbs à esquerda.
 * À direita, primeiro o `slot`, depois os botões.
 *
 * `breadcrumbs`: `{ text, to? }`. Sem `to`, o texto não é link. O último item é a página atual.
 * `buttons`: `{ title, icon?, to?, onClick?, disabled?, permission?, variant?, size? }`.
 * `slot`: qualquer coisa à direita, antes dos botões.
 */
export function LayoutToolbar({ breadcrumbs = [], buttons = [], slot }) {
    const hasActions = buttons.length > 0 || slot != null

    return (
        <div className="flex min-h-8 flex-wrap items-center gap-3 mb-6">
            {breadcrumbs.length > 0 ? (
                <Breadcrumb>
                    <BreadcrumbList>
                        {breadcrumbs.map((item, index) => {
                            const isCurrent = index === breadcrumbs.length - 1

                            return (
                                <Fragment key={`${item.text}-${index}`}>
                                    {index > 0 ? <BreadcrumbSeparator /> : null}
                                    <BreadcrumbItem>
                                        <BreadcrumbCrumb item={item} isCurrent={isCurrent} />
                                    </BreadcrumbItem>
                                </Fragment>
                            )
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            ) : null}

            {hasActions ? (
                <div className="ml-auto flex flex-wrap items-center gap-2">
                    {slot}
                    {buttons.map((button) => (
                        <ToolbarButton key={button.title} button={button} className={button.className} />
                    ))}
                </div>
            ) : null}
        </div>
    )
}

function BreadcrumbCrumb({ item, isCurrent }) {
    if (isCurrent || !item.to) {
        return isCurrent ? <BreadcrumbPage>{item.text}</BreadcrumbPage> : <span>{item.text}</span>
    }

    return <BreadcrumbLink render={<Link to={item.to} />}>{item.text}</BreadcrumbLink>
}

function ToolbarButton({ button, className }) {
    const Icon = button.icon
    const content = (
        <>
            {Icon ? <Icon data-icon="inline-start" /> : null}
            {button.title}
        </>
    )

    if (button.to) {
        return (
            <Button
                nativeButton={false}
                variant={button.variant}
                size={button.size}
                className={className}
                render={<Link to={button.to} />}
            >
                {content}
            </Button>
        )
    }

    return (
        <Button type="button" variant={button.variant} size={button.size} onClick={button.onClick} disabled={button.disabled} className={className}>
            {content}
        </Button>
    )
}
