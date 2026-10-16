import { createBrowserRouter } from 'react-router'
import Home from '../pages/Home'
import NotFound from '../pages/NotFound'
import Perimeter from '../pages/Perimeter'

export const router = createBrowserRouter([
  {
    path: '/',
    element: <Home />,
  },
  {
    path: '/perimeter',
    element: <Perimeter />,
  },
  {
    path: '*',
    element: <NotFound />,
  },
])