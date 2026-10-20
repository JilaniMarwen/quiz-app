import { Link } from 'react-router-dom'
import { useAdmin } from '../context/AdminContext.jsx'

/** Light layout for the professor pages. */
export default function AdminLayout({ title, back, actions, children }) {
  const { admin, logout } = useAdmin()

  return (
    <div className="min-h-dvh bg-slate-100 text-slate-800">
      <header className="bg-indigo-700 text-white shadow">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
          <Link to="/prof" className="text-lg font-black tracking-tight">
            Quiz<span className="text-yellow-300">Classe</span>
            <span className="ml-2 rounded bg-white/15 px-2 py-0.5 text-xs font-bold uppercase">Prof</span>
          </Link>
          {admin && (
            <button
              type="button"
              onClick={logout}
              className="rounded-lg px-3 py-1.5 text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white"
            >
              Déconnexion
            </button>
          )}
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-4 py-6">
        {back && (
          <Link
            to={back.to}
            // Optional guard (e.g. unsaved changes in the editor).
            onClick={(event) => back.confirm && !window.confirm(back.confirm) && event.preventDefault()}
            className="mb-3 inline-block text-sm font-semibold text-indigo-600 hover:underline"
          >
            ← {back.label}
          </Link>
        )}
        {(title || actions) && (
          <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 className="text-2xl font-black sm:text-3xl">{title}</h1>
            {actions}
          </div>
        )}
        {children}
      </main>
    </div>
  )
}

export function AdminSpinner() {
  return (
    <div className="flex justify-center py-16" role="status">
      <div className="h-10 w-10 animate-spin rounded-full border-4 border-indigo-200 border-t-indigo-600" />
    </div>
  )
}

export function AdminError({ message, onRetry }) {
  return (
    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800" role="alert">
      <p className="font-semibold">{message}</p>
      {onRetry && (
        <button type="button" onClick={onRetry} className="mt-2 text-sm font-bold underline">
          Réessayer
        </button>
      )}
    </div>
  )
}
