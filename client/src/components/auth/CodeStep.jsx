import { useEffect, useRef, useState } from 'react'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

export function maskEmail(email) {
    const [name, domain] = String(email ?? '').split('@')

    if (!domain) {
        return email
    }

    return `${name.slice(0, 1)}***@${domain}`
}

/**
 * Seis caixas para o código do e-mail.
 * Avança sozinho, volta com backspace e cola o código inteiro.
 */
export function CodeStep({ email, onSubmit, onResend, onChangeEmail, retryAfter = 60, submitting = false, error = '' }) {
    const [digits, setDigits] = useState(['', '', '', '', '', ''])
    const [seconds, setSeconds] = useState(retryAfter)
    const inputs = useRef([])

    useEffect(() => {
        setSeconds(retryAfter)
    }, [retryAfter])

    useEffect(() => {
        if (seconds <= 0) {
            return undefined
        }

        const timer = setTimeout(() => setSeconds((current) => current - 1), 1000)

        return () => clearTimeout(timer)
    }, [seconds])

    useEffect(() => {
        inputs.current[0]?.focus()
    }, [])

    function focusAt(index) {
        inputs.current[index]?.focus()
    }

    function fillFrom(text, start) {
        const chars = String(text).replace(/\D/g, '').slice(0, 6 - start).split('')
        const next = [...digits]

        chars.forEach((char, offset) => {
            next[start + offset] = char
        })

        setDigits(next)
        focusAt(Math.min(start + chars.length, 5))
    }

    function handleChange(index, value) {
        const clean = value.replace(/\D/g, '')

        if (clean.length > 1) {
            fillFrom(clean, index)
            return
        }

        const next = [...digits]
        next[index] = clean.slice(-1)
        setDigits(next)

        if (clean) {
            focusAt(Math.min(index + 1, 5))
        }
    }

    function handleKeyDown(index, event) {
        if (event.key === 'Backspace' && !digits[index] && index > 0) {
            const next = [...digits]
            next[index - 1] = ''
            setDigits(next)
            focusAt(index - 1)
            event.preventDefault()
        }
    }

    function handlePaste(event) {
        const text = event.clipboardData.getData('text')

        if (!text) {
            return
        }

        event.preventDefault()
        fillFrom(text, 0)
    }

    const code = digits.join('')

    async function handleResend() {
        const next = await onResend?.()

        if (typeof next === 'number' && next > 0) {
            setSeconds(next)
        }
    }

    return (
        <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
                event.preventDefault()

                if (code.length === 6 && !submitting) {
                    onSubmit(code)
                }
            }}
        >
            <p className="text-center text-sm text-muted-foreground">
                Enviamos para {maskEmail(email)}
            </p>
            <div className="flex justify-center gap-2" onPaste={handlePaste}>
                {digits.map((digit, index) => (
                    <div key={index} className="w-11">
                        <Input
                            ref={(node) => {
                                inputs.current[index] = node
                            }}
                            inputMode="numeric"
                            autoComplete={index === 0 ? 'one-time-code' : 'off'}
                            maxLength={1}
                            aria-label={`Dígito ${index + 1} do código`}
                            className="px-0 text-center text-lg"
                            value={digit}
                            onChange={(event) => handleChange(index, event.target.value)}
                            onKeyDown={(event) => handleKeyDown(index, event)}
                        />
                    </div>
                ))}
            </div>
            {error ? <p className="text-center text-sm text-destructive" role="alert">{error}</p> : null}
            <Button type="submit" disabled={submitting || code.length !== 6}>
                {submitting ? 'Confirmando...' : 'Confirmar código'}
            </Button>
            <div className="flex items-center justify-between gap-3 text-sm">
                {onChangeEmail ? (
                    <button type="button" className="underline underline-offset-4" onClick={onChangeEmail}>
                        Trocar e-mail
                    </button>
                ) : <span />}
                <button
                    type="button"
                    className="underline underline-offset-4 disabled:text-muted-foreground disabled:no-underline"
                    disabled={seconds > 0 || submitting}
                    onClick={handleResend}
                >
                    {seconds > 0 ? `Reenviar em ${seconds}s` : 'Reenviar código'}
                </button>
            </div>
        </form>
    )
}
