import './App.css'
import { ThemeProvider } from 'next-themes'
import { RouterProvider } from 'react-router/dom'
import { Toaster } from '@/components/ui/sonner'
import { router } from './router/router'

export default function App() {
  return (
    <ThemeProvider attribute="class" defaultTheme="system" enableSystem disableTransitionOnChange>
      <RouterProvider router={router} />
      <Toaster position="top-right" />
    </ThemeProvider>
  )
}