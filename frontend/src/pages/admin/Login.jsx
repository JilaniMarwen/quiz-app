import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { errorMessage } from '../../api/client.js'
import Layout from '../../components/Layout.jsx'
import { useAdmin } from '../../context/AdminContext.jsx'

export default function Login() {
  const { admin, login } = useAdmin()
  const navigate = useNavigate()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  if (admin) return <Navigate to="/prof" replace />

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError(null)
    try {
      await login(username, password)
      navigate('/prof')
    } catch (err) {
      setError(err.response?.status === 429 ? 'Trop de tentatives. Patiente une minute.' : errorMessage(err))
    } finally {
      setSubmitting(false)
    }
  }

  const input =
    'w-full rounded-xl border-2 border-slate-200 px-4 py-3 font-semibold text-slate-800 outline-none focus:border-indigo-500'

  return (
    <Layout>
      <div className="flex min-h-[75dvh] flex-col items-center justify-center">
        <form onSubmit={handleSubmit} className="w-full max-w-sm rounded-2xl bg-white p-6 text-slate-800 shadow-2xl">
          <h1 className="text-center text-2xl font-black">Compte professeur</h1>

          <label htmlFor="username" className="mt-6 block text-sm font-bold">Nom d'utilisateur</label>
          <input
            id="username"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            autoComplete="username"
            autoFocus
            required
            className={`mt-1 ${input}`}
          />

          <label htmlFor="password" className="mt-4 block text-sm font-bold">Mot de passe</label>
          <input
            id="password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="current-password"
            required
            className={`mt-1 ${input}`}
          />

          {error && <p className="mt-3 text-center text-sm font-semibold text-red-600" role="alert">{error}</p>}

          <button
            type="submit"
            disabled={submitting}
            className="mt-6 w-full rounded-xl bg-indigo-600 py-3 text-lg font-black text-white hover:bg-indigo-700 disabled:opacity-60"
          >
            {submitting ? 'Connexion…' : 'Se connecter'}
          </button>
        </form>

        <Link to="/" className="mt-6 text-sm font-semibold text-white/70 hover:text-white hover:underline">
          ← Retour à l'accueil
        </Link>
      </div>
    </Layout>
  )
}
