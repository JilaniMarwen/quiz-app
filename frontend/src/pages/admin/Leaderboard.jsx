import { useCallback, useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import client, { errorMessage } from '../../api/client.js'
import AdminLayout, { AdminError, AdminSpinner } from '../../components/AdminLayout.jsx'

const REFRESH_MS = 3000

function formatDuration(seconds) {
  if (seconds === null) return '—'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return m ? `${m} min ${String(s).padStart(2, '0')} s` : `${s} s`
}

const PODIUM = {
  1: { height: 'h-36', color: 'bg-yellow-400', medal: '🥇' },
  2: { height: 'h-28', color: 'bg-slate-300', medal: '🥈' },
  3: { height: 'h-20', color: 'bg-amber-600', medal: '🥉' },
}

function Podium({ entries }) {
  const top = entries.slice(0, 3)
  // Display order: 2nd, 1st, 3rd.
  const ordered = [top[1], top[0], top[2]].filter(Boolean)

  return (
    <div className="mb-8 flex items-end justify-center gap-2 sm:gap-4">
      {ordered.map((entry) => {
        const place = PODIUM[Math.min(entry.rank, 3)]
        return (
          <div key={entry.attempt_id} className="flex w-28 flex-col items-center sm:w-40">
            <span className="text-3xl" aria-hidden="true">{place.medal}</span>
            <span className="mt-1 w-full truncate text-center font-black">{entry.name}</span>
            <span className="text-sm font-bold text-slate-600">
              {entry.score} / {entry.total}
            </span>
            <div className={`mt-2 flex w-full items-start justify-center rounded-t-xl pt-2 text-2xl font-black text-white ${place.height} ${place.color}`}>
              {entry.rank}
            </div>
          </div>
        )
      })}
    </div>
  )
}

export default function Leaderboard() {
  const { id } = useParams()
  const [board, setBoard] = useState(null)
  const [error, setError] = useState(null)

  const load = useCallback(async () => {
    try {
      const { data } = await client.get(`/admin/quizzes/${id}/leaderboard`)
      setBoard(data)
      setError(null)
    } catch (err) {
      setError(errorMessage(err))
    }
  }, [id])

  useEffect(() => {
    load()
    const timer = setInterval(load, REFRESH_MS)
    return () => clearInterval(timer)
  }, [load])

  const entries = board?.entries ?? []
  const playing = entries.filter((e) => !e.finished).length

  return (
    <AdminLayout back={{ to: '/prof', label: 'Mes quiz' }} title={board ? `Classement — ${board.quiz.title}` : 'Classement'}>
      {error && <AdminError message={error} onRetry={load} />}
      {!board && !error && <AdminSpinner />}

      {board && (
        <>
          <div className="mb-6 flex flex-wrap items-center gap-2 text-sm font-semibold">
            <span className={`rounded-full px-3 py-1 ${board.quiz.is_open ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-600'}`}>
              {board.quiz.is_open ? 'Ouvert' : 'Fermé'}
            </span>
            <span className="rounded-full bg-white px-3 py-1 shadow-sm">
              {entries.length} participant{entries.length > 1 ? 's' : ''}
            </span>
            {playing > 0 && (
              <span className="rounded-full bg-indigo-100 px-3 py-1 text-indigo-800">{playing} en train de jouer</span>
            )}
            <span className="flex items-center gap-1.5 text-slate-500">
              <span className="h-2 w-2 animate-pulse rounded-full bg-green-500" /> mise à jour automatique
            </span>
          </div>

          {entries.length === 0 ? (
            <div className="rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
              Personne n'a encore joué à ce quiz.
            </div>
          ) : (
            <>
              <Podium entries={entries} />

              <div className="overflow-x-auto rounded-2xl bg-white shadow-sm">
                <table className="w-full text-left">
                  <thead className="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                      <th className="px-4 py-3">Rang</th>
                      <th className="px-4 py-3">Nom</th>
                      <th className="px-4 py-3">Score</th>
                      <th className="px-4 py-3">Temps</th>
                    </tr>
                  </thead>
                  <tbody>
                    {entries.map((entry) => (
                      <tr key={entry.attempt_id} className="border-b border-slate-100 last:border-0">
                        <td className="px-4 py-3 font-black">{entry.rank}</td>
                        <td className="px-4 py-3 font-semibold">{entry.name}</td>
                        <td className="px-4 py-3 font-bold">
                          {entry.score} / {entry.total}
                        </td>
                        <td className="px-4 py-3 text-slate-600">
                          {entry.finished ? (
                            formatDuration(entry.duration_seconds)
                          ) : (
                            <span className="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-bold text-indigo-700">en cours</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          )}
        </>
      )}
    </AdminLayout>
  )
}
