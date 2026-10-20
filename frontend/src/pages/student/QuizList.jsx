import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import client, { errorMessage } from '../../api/client.js'
import { ErrorBox, Spinner } from '../../components/Feedback.jsx'
import Layout from '../../components/Layout.jsx'
import QuizCard from '../../components/QuizCard.jsx'
import { useStudent } from '../../context/StudentContext.jsx'

// The teacher opens the quiz in class: refresh the list so it appears without reloading.
const REFRESH_MS = 5000

export default function QuizList() {
  const { student } = useStudent()
  const navigate = useNavigate()
  const [quizzes, setQuizzes] = useState(null)
  const [error, setError] = useState(null)
  const [startingId, setStartingId] = useState(null)

  const load = useCallback(async () => {
    try {
      const { data } = await client.get('/student/quizzes')
      setQuizzes(data.data)
      setError(null)
    } catch (err) {
      setError(errorMessage(err))
    }
  }, [])

  useEffect(() => {
    load()
    const id = setInterval(load, REFRESH_MS)
    return () => clearInterval(id)
  }, [load])

  async function start(quiz) {
    if (quiz.status === 'in_progress') {
      navigate(`/play/${quiz.attempt_id}`)
      return
    }
    setStartingId(quiz.id)
    try {
      const { data } = await client.post(`/student/quizzes/${quiz.id}/start`)
      navigate(`/play/${data.attempt.id}`)
    } catch (err) {
      const attempt = err.response?.status === 409 && err.response.data.attempt
      if (attempt) {
        navigate(attempt.status === 'done' ? `/result/${attempt.id}` : `/play/${attempt.id}`)
      } else {
        setError(errorMessage(err))
        load()
      }
    } finally {
      setStartingId(null)
    }
  }

  return (
    <Layout right={<span className="truncate rounded-full bg-white/15 px-3 py-1 text-sm font-bold">{student?.name}</span>}>
      <h1 className="mb-6 text-3xl font-black">Les quiz</h1>

      {error && <ErrorBox message={error} onRetry={load} />}

      {quizzes === null && !error && <Spinner />}

      {quizzes?.length === 0 && (
        <div className="rounded-2xl bg-white/10 p-8 text-center">
          <p className="text-lg font-bold">Aucun quiz ouvert pour le moment.</p>
          <p className="mt-2 text-white/70">Attends que ton professeur en ouvre un : il apparaîtra ici automatiquement.</p>
        </div>
      )}

      <div className="flex flex-col gap-4">
        {quizzes?.map((quiz) => (
          <QuizCard key={quiz.id} quiz={quiz} onStart={start} starting={startingId === quiz.id} />
        ))}
      </div>
    </Layout>
  )
}
