import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { errorMessage } from '../../api/client.js'
import Layout from '../../components/Layout.jsx'
import { useStudent } from '../../context/StudentContext.jsx'

export default function Home() {
  const { student, status, register } = useStudent()
  const navigate = useNavigate()
  const [name, setName] = useState('')
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  // Already signed in on this device (token in localStorage).
  if (student || status === 'loading' || status === 'offline') {
    return <Navigate to="/quizzes" replace />
  }

  async function handleSubmit(event) {
    event.preventDefault()
    if (!name.trim()) {
      setError('Entre ton nom.')
      return
    }
    setSubmitting(true)
    setError(null)
    try {
      await register(name)
      navigate('/quizzes')
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Layout>
      <div className="flex min-h-[75dvh] flex-col items-center justify-center">
        <h1 className="text-center text-5xl font-black tracking-tight sm:text-6xl">
          Quiz<span className="text-yellow-300">Classe</span>
        </h1>
        <p className="mt-3 text-center text-lg text-white/80">Entre ton nom pour participer.</p>

        <form onSubmit={handleSubmit} className="mt-8 w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl" noValidate>
          <label htmlFor="name" className="sr-only">Ton nom</label>
          <input
            id="name"
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Ton nom"
            maxLength={30}
            autoComplete="off"
            autoFocus
            className="w-full rounded-xl border-2 border-slate-200 px-4 py-3 text-center text-lg font-bold text-slate-800 outline-none focus:border-indigo-500"
            aria-invalid={!!error}
            aria-describedby={error ? 'name-error' : undefined}
          />
          {error && (
            <p id="name-error" className="mt-2 text-center text-sm font-semibold text-red-600">{error}</p>
          )}
          <button
            type="submit"
            disabled={submitting}
            className="mt-4 w-full rounded-xl bg-slate-900 py-3 text-lg font-black text-white hover:bg-slate-800 disabled:opacity-60"
          >
            {submitting ? 'Connexion…' : 'Entrer'}
          </button>
        </form>

        <Link
          to="/prof/login"
          className="mt-8 text-sm font-semibold text-white/70 underline-offset-4 hover:text-white hover:underline"
        >
          Compte professeur
        </Link>
      </div>
    </Layout>
  )
}
