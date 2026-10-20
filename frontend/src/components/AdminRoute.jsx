import { Navigate, Outlet } from 'react-router-dom'
import { useAdmin } from '../context/AdminContext.jsx'
import AdminLayout, { AdminError, AdminSpinner } from './AdminLayout.jsx'

/** Professor pages: wait for the token check, redirect to the login page otherwise. */
export default function AdminRoute() {
  const { admin, status, retry } = useAdmin()

  if (status === 'loading') {
    return <AdminLayout><AdminSpinner /></AdminLayout>
  }
  if (status === 'offline') {
    return <AdminLayout><AdminError message="Impossible de joindre le serveur." onRetry={retry} /></AdminLayout>
  }
  if (!admin) {
    return <Navigate to="/prof/login" replace />
  }
  return <Outlet />
}
