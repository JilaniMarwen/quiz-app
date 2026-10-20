import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import client, { errorMessage } from '../../api/client.js'
import AdminLayout, { AdminError, AdminSpinner } from '../../components/AdminLayout.jsx'
import ShareCard from '../../components/ShareCard.jsx'

function Toggle({ checked, onChange, disabled, label }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      onClick={onChange}
      disabled={disabled}
      className="flex items-center gap-2 disabled:opacity-60"
    >
      <span className={`relative h-7 w-12 rounded-full transition ${checked ? 'bg-green-500' : 'bg-slate-300'}`}>
        <span
          className={`absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-all ${checked ? 'left-6' : 'left-1'}`}
        />
      </span>
      <span className={`text-sm font-bold ${checked ? 'text-green-700' : 'text-slate-500'}`}>
        {checked ? 'Ouvert' : 'Fermé'}
      </span>
    </button>
  )
}

export default function Dashboard() {
  const [quizzes, setQuizzes] = useState(null)
  const [error, setError] = useState(null)
  const [busyId, setBusyId] = useState(null)
  const [notice, setNotice] = useState(null)

  const load = useCallback(async () => {
    try {
      const { data } = await client.get('/admin/quizzes')
      setQuizzes(data.data)
      setError(null)
    } catch (err) {
      setError(errorMessage(err))
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  async function toggle(quiz) {
    setBusyId(quiz.id)
    setNotice(null)
    try {
      const { data } = await client.patch(`/admin/quizzes/${quiz.id}/toggle`)
      setQuizzes((list) => list.map((q) => (q.id === quiz.id ? data.data : q)))
    } catch (err) {
      setNotice({ id: quiz.id, message: errorMessage(err) })
    } finally {
      setBusyId(null)
    }
  }

  async function remove(quiz) {
    const warning = quiz.participants_count > 0 ? `\n${quiz.participants_count} résultat(s) seront aussi supprimés.` : ''
    if (!window.confirm(`Supprimer le quiz « ${quiz.title} » ?${warning}`)) return
    setBusyId(quiz.id)
    try {
      await client.delete(`/admin/quizzes/${quiz.id}`)
      setQuizzes((list) => list.filter((q) => q.id !== quiz.id))
    } catch (err) {
      setNotice({ id: quiz.id, message: errorMessage(err) })
    } finally {
      setBusyId(null)
    }
  }

  const button = 'rounded-lg px-3 py-2 text-sm font-bold'

  return (
    <AdminLayout
      title="Mes quiz"
      actions={
        <Link to="/prof/quizzes/new" className="rounded-xl bg-indigo-600 px-4 py-2.5 font-black text-white shadow hover:bg-indigo-700">
          + Nouveau quiz
        </Link>
      }
    >
      <ShareCard />

      {error && <AdminError message={error} onRetry={load} />}
      {!quizzes && !error && <AdminSpinner />}

      {quizzes?.length === 0 && (
        <div className="rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
          Aucun quiz pour l'instant. Crée ton premier quiz !
        </div>
      )}

      <div className="flex flex-col gap-3">
        {quizzes?.map((quiz) => (
          <article key={quiz.id} className="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
              <div className="min-w-0 flex-1">
                <h2 className="truncate text-lg font-black">{quiz.title}</h2>
                <p className="text-sm text-slate-500">
                  {quiz.questions_count} question{quiz.questions_count > 1 ? 's' : ''} · {quiz.time_per_question} s ·{' '}
                  {quiz.participants_count} participant{quiz.participants_count > 1 ? 's' : ''}
                </p>
              </div>

              <Toggle
                checked={quiz.is_open}
                onChange={() => toggle(quiz)}
                disabled={busyId === quiz.id}
                label={`Ouvrir ou fermer ${quiz.title}`}
              />

              <div className="flex flex-wrap gap-2">
                <Link to={`/prof/quizzes/${quiz.id}/edit`} className={`${button} bg-slate-100 hover:bg-slate-200`}>
                  Modifier
                </Link>
                <Link to={`/prof/quizzes/${quiz.id}/leaderboard`} className={`${button} bg-amber-100 text-amber-800 hover:bg-amber-200`}>
                  Classement
                </Link>
                <button
                  type="button"
                  onClick={() => remove(quiz)}
                  disabled={busyId === quiz.id}
                  className={`${button} bg-red-50 text-red-700 hover:bg-red-100`}
                >
                  Supprimer
                </button>
              </div>
            </div>

            {notice?.id === quiz.id && (
              <p className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm font-semibold text-red-700" role="alert">
                {notice.message}
              </p>
            )}
          </article>
        ))}
      </div>
    </AdminLayout>
  )
}
