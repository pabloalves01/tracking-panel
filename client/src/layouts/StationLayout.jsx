import { useEffect } from 'react'
import { Outlet } from 'react-router'

import { ReadOnlyHint } from '@/components/common/ReadOnlyHint'
import { AppFooter } from '@/components/layout/app/AppFooter'
import { StationHeader } from '@/components/layout/station/StationHeader'
import { StationSidebar } from '@/components/layout/station/StationSidebar'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { TooltipProvider } from '@/components/ui/tooltip'
import { useCanWrite } from '@/lib/auth/permissions'

export function StationLayout() {
    const canWrite = useCanWrite()

    useEffect(() => {
        if (canWrite) {
            return undefined
        }

        function blockSubmit(event) {
            const form = event.target

            if (form instanceof HTMLFormElement && form.id.endsWith('-form')) {
                event.preventDefault()
                event.stopPropagation()
            }
        }

        document.addEventListener('submit', blockSubmit, true)

        return () => document.removeEventListener('submit', blockSubmit, true)
    }, [canWrite])

    return (
        <TooltipProvider>
            <SidebarProvider>
                <StationSidebar />
                <SidebarInset className="min-h-svh">
                    <StationHeader />
                    <div className="flex min-h-0 flex-1 flex-col overflow-auto p-6">
                        <ReadOnlyHint />
                        <Outlet />
                    </div>
                    <AppFooter />
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    )
}
