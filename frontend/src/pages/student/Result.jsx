import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, Navigate, useParams } from 'react-router-dom'
import client, { errorMessage } from '../../api/client.js'
import { ErrorBox, Spinner } from '../../components/Feedback.jsx'
import Layout from '../../components/Layout.jsx'
import { playChime, throwConfetti } from '../../utils/celebrate.js'

function verdict(ratio) {
  if (ratio === 1) return { text: 'Parfait ! 🏆', color: 'text-green-600' }
  if (ratio >= 0.7) return { text: 'Excellent travail ! 🎉', color: 'text-green-600' }
  if (ratio >= 0.5) return { text: 'Bien joué ! 👍', color: 'text-amber-600' }
  return { text: 'Continue tes efforts ! 💪', color: 'text-red-600' }
}

export default function Result() {
  const { attemptId } = useParams()
  const [result, setResult] = useState(null)
  const [error, setError] = useState(null)
  const [inProgress, setInProgress] = useState(false)

  const load = useCallback(async () => {
    setError(null)
    try {
      const { data } = await client.get(`/student/attempts/${attemptId}/result`)
      setResult(data)
    } catch (err) {
      if (err.response?.status === 409) setInProgress(true)
      else setError(errorMessage(err))
    }
  }, [attemptId])

  useEffect(() => {
    load()
  }, [load])

  // Celebrate once when the score is shown.
  const celebrated = useRef(false)
  useEffect(() => {
    if (!result || celebrated.current) return
    celebrated.current = true
    const success = result.total > 0 && result.score / result.total >= 0.5
    playChime(success)
    if (success) throwConfetti()
  }, [result])

  if (inProgress) return <Navigate to={`/play/${attemptId}`} replace />

  const ratio = result && result.total > 0 ? result.score / result.total : 0
  const { text, color } = verdict(ratio)

  return (
    <Layout>
      {error && <ErrorBox message={error} onRetry={load} />}
      {!result && !error && <Spinner />}
      {result && (
        <div className="flex min-h-[75dvh] flex-col items-center justify-center">
          <div className="w-full max-w-sm animate-pop-in rounded-3xl bg-white p-8 motion-reduce:animate-none text-center text-slate-800 shadow-2xl">
            <p className="text-sm font-semibold text-slate-500">{result.quiz.title}</p>
            <p className="mt-4 text-sm font-bold uppercase tracking-wide text-slate-400">Ton score</p>
            <p className={`text-7xl font-black ${color}`}>
              {result.score}
              <span className="text-4xl text-slate-400"> / {result.total}</span>
            </p>
            <p className="mt-4 text-xl font-black">{text}</p>
          </div>
          <Link
            to="/quizzes"
            className="mt-8 rounded-xl bg-yellow-300 px-6 py-3 text-lg font-black text-slate-900 shadow-lg hover:bg-yellow-200"
          >
            Retour aux quiz
          </Link>
        </div>
      )}
    </Layout>
  )
}
