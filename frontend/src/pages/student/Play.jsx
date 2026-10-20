import { useCallback, useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import client, { errorMessage } from '../../api/client.js'
import ChoiceButton from '../../components/ChoiceButton.jsx'
import { ErrorBox, Spinner } from '../../components/Feedback.jsx'
import Layout from '../../components/Layout.jsx'
import Timer from '../../components/Timer.jsx'

/**
 * One question at a time. The server is the source of truth: it decides the current question
 * and how much time is left; this page only displays it and auto-sends `choice_id: null` at 0.
 */
export default function Play() {
  const { attemptId } = useParams()
  const navigate = useNavigate()
  const [state, setState] = useState(null)
  const [deadline, setDeadline] = useState(null)
  const [selected, setSelected] = useState(null)
  const [error, setError] = useState(null)
  const sending = useRef(false)

  const apply = useCallback(
    (data) => {
      if (data.status === 'finished') {
        navigate(`/result/${attemptId}`, { replace: true })
        return
      }
      setState(data)
      setSelected(null)
      setDeadline(Date.now() + data.remaining_seconds * 1000)
    },
    [attemptId, navigate],
  )

  const load = useCallback(async () => {
    setError(null)
    try {
      const { data } = await client.get(`/student/attempts/${attemptId}/current`)
      apply(data)
    } catch (err) {
      setError(errorMessage(err, 'Impossible de charger la question.'))
    }
  }, [attemptId, apply])

  useEffect(() => {
    load()
  }, [load])

  // Back on the tab (phone locked, app switched): resync with the server timer.
  useEffect(() => {
    const onVisible = () => {
      if (document.visibilityState === 'visible' && !sending.current) load()
    }
    document.addEventListener('visibilitychange', onVisible)
    return () => document.removeEventListener('visibilitychange', onVisible)
  }, [load])

  const send = useCallback(
    async (choiceId) => {
      if (sending.current || !state) return
      sending.current = true
      setSelected(choiceId ?? 'timeout')
      try {
        const { data } = await client.post(`/student/attempts/${attemptId}/answer`, {
          question_id: state.question.id,
          choice_id: choiceId,
        })
        apply(data)
      } catch (err) {
        // 409: this question already expired on the server, fetch the current one.
        if (err.response?.status === 409) await load()
        else setError(errorMessage(err, 'Ta réponse n\'a pas pu être envoyée.'))
      } finally {
        sending.current = false
      }
    },
    [state, attemptId, apply, load],
  )

  const handleExpire = useCallback(() => send(null), [send])

  if (error) {
    return <Layout><ErrorBox message={error} onRetry={load} /></Layout>
  }
  if (!state) {
    return <Layout><Spinner /></Layout>
  }

  const answered = selected !== null

  return (
    <Layout
      wide
      right={
        <span className="rounded-full bg-white/15 px-3 py-1 text-sm font-black">
          {state.index} / {state.total}
        </span>
      }
    >
      <div className="flex min-h-[80dvh] flex-col gap-5">
        <p className="truncate text-sm font-semibold text-white/70">{state.quiz.title}</p>

        <Timer
          key={state.question.id}
          deadline={deadline}
          duration={state.time_per_question}
          onExpire={handleExpire}
          paused={answered}
        />

        <h1
          key={`q-${state.question.id}`}
          className="flex flex-1 animate-pop-in items-center motion-reduce:animate-none justify-center rounded-2xl bg-white p-6 text-center text-2xl font-black text-slate-800 shadow-xl sm:text-3xl">
          {state.question.text}
        </h1>

        <div key={`c-${state.question.id}`} className="grid animate-pop-in grid-cols-1 gap-3 motion-reduce:animate-none sm:grid-cols-2">
          {state.question.choices.map((choice, i) => (
            <ChoiceButton
              key={choice.id}
              index={i}
              text={choice.text}
              onClick={() => send(choice.id)}
              disabled={answered}
              selected={selected === choice.id}
              dimmed={answered && selected !== choice.id}
            />
          ))}
        </div>

        {answered && (
          <p className="text-center font-semibold text-white/80">
            {selected === 'timeout' ? 'Temps écoulé !' : 'Réponse envoyée…'}
          </p>
        )}
      </div>
    </Layout>
  )
}
