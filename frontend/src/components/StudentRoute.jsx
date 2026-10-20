import { Navigate, Outlet } from 'react-router-dom'
import { useStudent } from '../context/StudentContext.jsx'
import { ErrorBox, Spinner } from './Feedback.jsx'
import Layout from './Layout.jsx'

/** Student pages: wait for the token check, redirect to the home page if there is no student. */
export default function StudentRoute() {
  const { student, status, retry } = useStudent()

  if (status === 'loading') {
    return <Layout><Spinner /></Layout>
  }
  if (status === 'offline') {
    return <Layout><ErrorBox message="Impossible de joindre le serveur. Vérifie ta connexion." onRetry={retry} /></Layout>
  }
  if (!student) {
    return <Navigate to="/" replace />
  }
  return <Outlet />
}
