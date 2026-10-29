import { useCallback, useEffect, useState } from 'react'

import { notifyError } from '@/lib/notify'

/**
 * Uma página de uma lista paginada da API.
 *
 * `fetcher(params)` devolve a resposta com `data` e `meta`. A lista é
 * buscada de novo quando `params` muda ou quando `reload()` é chamado.
 * `loading` é verdadeiro enquanto a resposta não for a dos parâmetros
 * atuais — sem `setState` dentro do efeito.
 */
export function useRemoteList(fetcher, params) {
    const key = JSON.stringify(params)
    const [version, setVersion] = useState(0)
    const [result, setResult] = useState({ key: null, data: [], meta: null })

    useEffect(() => {
        let active = true
        const requested = `${key}:${version}`

        fetcher(JSON.parse(key))
            .then((response) => {
                if (active) setResult({ key: requested, data: response.data ?? [], meta: response.meta ?? null })
            })
            .catch((error) => {
                if (!active) return
                notifyError(error)
                setResult({ key: requested, data: [], meta: null })
            })

        return () => {
            active = false
        }
    }, [fetcher, key, version])

    const reload = useCallback(() => setVersion((current) => current + 1), [])

    return {
        records: result.data,
        meta: result.meta,
        loading: result.key !== `${key}:${version}`,
        reload,
    }
}
