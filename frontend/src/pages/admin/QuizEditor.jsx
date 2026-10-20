import { useCallback, useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import client, { errorMessage } from '../../api/client.js'
import AdminLayout, { AdminError, AdminSpinner } from '../../components/AdminLayout.jsx'

const CHOICE_COLORS = ['border-red-500', 'border-blue-500', 'border-amber-500', 'border-green-600']
const CHOICE_SHAPES = ['▲', '◆', '●', '■']

let draftCounter = 0
const emptyQuestion = () => ({
  key: `draft-${++draftCounter}`,
  id: null,
  text: '',
  choices: ['', '', '', ''],
  correct: null,
  dirty: true,
  error: null,
})

const fromServer = (question) => ({
  key: `q-${question.id}`,
  id: question.id,
  text: question.text,
  choices: question.choices.map((choice) => choice.text),
  correct: question.choices.findIndex((choice) => choice.is_correct),
  dirty: false,
  error: null,
})

const toPayload = (question) => ({
  text: question.text.trim(),
  choices: question.choices.map((text, i) => ({ text: text.trim(), is_correct: i === question.correct })),
})

/** Same rules as the API (4 non-empty choices, exactly 1 correct), checked before sending. */
function validate(question) {
  if (!question.text.trim()) return 'Écris le texte de la question.'
  if (question.choices.some((choice) => !choice.trim())) return 'Remplis les 4 réponses.'
  if (question.correct === null || question.correct < 0) return 'Choisis la bonne réponse.'
  return null
}

export default function QuizEditorPage() {
  const { id } = useParams()
  // Remount when switching between "new" and an existing quiz.
  return <QuizEditor key={id ?? 'new'} id={id} />
}

function QuizEditor({ id }) {
  const navigate = useNavigate()
  const [quizId, setQuizId] = useState(id ?? null)
  const [info, setInfo] = useState({ title: '', description: '', time_per_question: 20 })
  const [infoDirty, setInfoDirty] = useState(!id)
  const [meta, setMeta] = useState({ is_open: false, participants_count: 0 })
  const [questions, setQuestions] = useState(() => (id ? [] : [emptyQuestion()]))
  const [loading, setLoading] = useState(!!id)
  const [loadError, setLoadError] = useState(null)
  const [saving, setSaving] = useState(false)
  const [banner, setBanner] = useState(null)

  const applyQuiz = useCallback((quiz) => {
    setInfo({ title: quiz.title, description: quiz.description ?? '', time_per_question: quiz.time_per_question })
    setMeta({ is_open: quiz.is_open, participants_count: quiz.participants_count ?? 0 })
  }, [])

  const load = useCallback(async () => {
    setLoading(true)
    setLoadError(null)
    try {
      const { data } = await client.get(`/admin/quizzes/${id}`)
      applyQuiz(data.data)
      setQuestions(data.data.questions.map(fromServer))
      setInfoDirty(false)
    } catch (err) {
      setLoadError(errorMessage(err, 'Quiz introuvable.'))
    } finally {
      setLoading(false)
    }
  }, [id, applyQuiz])

  useEffect(() => {
    if (id) load()
  }, [id, load])

  const hasUnsaved = infoDirty || questions.some((q) => q.dirty)

  // Warn before closing / reloading the tab with unsaved changes.
  useEffect(() => {
    if (!hasUnsaved) return
    const handler = (event) => event.preventDefault()
    window.addEventListener('beforeunload', handler)
    return () => window.removeEventListener('beforeunload', handler)
  }, [hasUnsaved])

  function updateInfo(field, value) {
    setInfo((current) => ({ ...current, [field]: value }))
    setInfoDirty(true)
  }

  function updateQuestion(key, changes) {
    setQuestions((list) => list.map((q) => (q.key === key ? { ...q, ...changes, dirty: true, error: null } : q)))
  }

  function updateChoice(key, index, value) {
    setQuestions((list) =>
      list.map((q) =>
        q.key === key
          ? { ...q, choices: q.choices.map((c, i) => (i === index ? value : c)), dirty: true, error: null }
          : q,
      ),
    )
  }

  async function removeQuestion(question) {
    if (!question.id) {
      setQuestions((list) => list.filter((q) => q.key !== question.key))
      return
    }
    if (!window.confirm('Supprimer cette question ?')) return
    try {
      await client.delete(`/admin/questions/${question.id}`)
      setQuestions((list) => list.filter((q) => q.key !== question.key))
    } catch (err) {
      setQuestions((list) => list.map((q) => (q.key === question.key ? { ...q, error: errorMessage(err) } : q)))
    }
  }

  async function resetResults() {
    if (!window.confirm(`Supprimer les résultats des ${meta.participants_count} participant(s) ? Ils pourront rejouer ce quiz.`)) return
    try {
      const { data } = await client.delete(`/admin/quizzes/${quizId}/attempts`)
      applyQuiz(data.data)
      setBanner({ type: 'success', message: 'Résultats réinitialisés.' })
    } catch (err) {
      setBanner({ type: 'error', message: errorMessage(err) })
    }
  }

  async function saveAll() {
    setBanner(null)

    // 1. Client-side validation of everything that will be sent.
    let invalid = !info.title.trim()
    const checked = questions.map((q) => {
      if (!q.dirty) return q
      const error = validate(q)
      if (error) invalid = true
      return { ...q, error }
    })
    setQuestions(checked)
    if (invalid) {
      setBanner({ type: 'error', message: info.title.trim() ? 'Certaines questions sont incomplètes.' : 'Le titre est obligatoire.' })
      return
    }

    setSaving(true)
    let failures = 0
    let currentId = quizId
    try {
      // 2. Quiz info (creates the quiz the first time).
      const payload = {
        title: info.title.trim(),
        description: info.description.trim() || null,
        time_per_question: Number(info.time_per_question),
      }
      if (!currentId) {
        const { data } = await client.post('/admin/quizzes', payload)
        currentId = data.data.id
        setQuizId(currentId)
        applyQuiz(data.data)
      } else if (infoDirty) {
        const { data } = await client.put(`/admin/quizzes/${currentId}`, payload)
        applyQuiz(data.data)
      }
      setInfoDirty(false)
    } catch (err) {
      setBanner({ type: 'error', message: errorMessage(err) })
      setSaving(false)
      return
    }

    // 3. Each new / modified question.
    for (const question of checked.filter((q) => q.dirty)) {
      try {
        const { data } = question.id
          ? await client.put(`/admin/questions/${question.id}`, toPayload(question))
          : await client.post(`/admin/quizzes/${currentId}/questions`, toPayload(question))
        setQuestions((list) => list.map((q) => (q.key === question.key ? fromServer(data.data) : q)))
      } catch (err) {
        failures++
        setQuestions((list) => list.map((q) => (q.key === question.key ? { ...q, error: errorMessage(err) } : q)))
      }
    }
    setSaving(false)

    if (failures) {
      setBanner({ type: 'error', message: `${failures} question(s) n'ont pas pu être enregistrées.` })
    } else if (!id) {
      navigate(`/prof/quizzes/${currentId}/edit`, { replace: true })
    } else {
      setBanner({ type: 'success', message: 'Modifications enregistrées ✓' })
    }
  }

  const input = 'w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100'

  if (loading) return <AdminLayout back={{ to: '/prof', label: 'Mes quiz' }}><AdminSpinner /></AdminLayout>
  if (loadError) return <AdminLayout back={{ to: '/prof', label: 'Mes quiz' }}><AdminError message={loadError} onRetry={load} /></AdminLayout>

  return (
    <AdminLayout
      back={{ to: '/prof', label: 'Mes quiz', confirm: hasUnsaved && 'Quitter sans enregistrer les modifications ?' }}
      title={id ? 'Modifier le quiz' : 'Nouveau quiz'}
    >
      <div className="flex flex-col gap-4 pb-24">
        {/* Rule 9: modifying a quiz that already has results */}
        {meta.participants_count > 0 && (
          <div className="flex flex-col gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 sm:flex-row sm:items-center">
            <p className="flex-1 text-sm font-semibold">
              ⚠️ Ce quiz a déjà {meta.participants_count} participant{meta.participants_count > 1 ? 's' : ''}. Modifier les
              questions fausserait leurs scores.
            </p>
            <button type="button" onClick={resetResults} className="rounded-lg bg-amber-600 px-3 py-2 text-sm font-bold text-white hover:bg-amber-700">
              Réinitialiser les résultats
            </button>
          </div>
        )}
        {meta.is_open && (
          <p className="rounded-xl bg-green-50 p-3 text-sm font-semibold text-green-800">
            Ce quiz est ouvert : les étudiants le voient en ce moment.
          </p>
        )}

        <section className="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
          <h2 className="mb-3 font-black">Informations</h2>
          <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
            <div>
              <label htmlFor="title" className="block text-sm font-bold">Titre</label>
              <input id="title" value={info.title} onChange={(e) => updateInfo('title', e.target.value)} maxLength={255} className={`mt-1 ${input}`} />
            </div>
            <div>
              <label htmlFor="time" className="block text-sm font-bold">Temps par question (s)</label>
              <input
                id="time"
                type="number"
                min={5}
                max={300}
                value={info.time_per_question}
                onChange={(e) => updateInfo('time_per_question', e.target.value)}
                className={`mt-1 sm:w-40 ${input}`}
              />
            </div>
          </div>
          <label htmlFor="description" className="mt-3 block text-sm font-bold">
            Description <span className="font-normal text-slate-400">(optionnel)</span>
          </label>
          <textarea
            id="description"
            value={info.description}
            onChange={(e) => updateInfo('description', e.target.value)}
            rows={2}
            maxLength={1000}
            className={`mt-1 ${input}`}
          />
        </section>

        {questions.map((question, qi) => (
          <section
            key={question.key}
            className={`rounded-2xl bg-white p-4 shadow-sm sm:p-5 ${question.error ? 'ring-2 ring-red-300' : ''}`}
          >
            <div className="mb-3 flex items-center justify-between gap-2">
              <h2 className="font-black">
                Question {qi + 1}
                {question.dirty && <span className="ml-2 text-xs font-semibold text-amber-600">non enregistrée</span>}
              </h2>
              <button type="button" onClick={() => removeQuestion(question)} className="rounded-lg px-2 py-1 text-sm font-bold text-red-600 hover:bg-red-50">
                Supprimer
              </button>
            </div>

            <label htmlFor={`text-${question.key}`} className="sr-only">Texte de la question</label>
            <textarea
              id={`text-${question.key}`}
              value={question.text}
              onChange={(e) => updateQuestion(question.key, { text: e.target.value })}
              placeholder="Texte de la question"
              rows={2}
              maxLength={1000}
              className={input}
            />

            <fieldset className="mt-3">
              <legend className="mb-2 text-sm font-bold">
                Réponses <span className="font-normal text-slate-500">— coche la bonne</span>
              </legend>
              <div className="grid gap-2 sm:grid-cols-2">
                {question.choices.map((choice, ci) => (
                  <label
                    key={ci}
                    className={`flex items-center gap-2 rounded-lg border-l-4 bg-slate-50 p-2 ${CHOICE_COLORS[ci]} ${question.correct === ci ? 'bg-green-50 ring-2 ring-green-400' : ''}`}
                  >
                    <input
                      type="radio"
                      name={`correct-${question.key}`}
                      checked={question.correct === ci}
                      onChange={() => updateQuestion(question.key, { correct: ci })}
                      className="h-5 w-5 accent-green-600"
                      aria-label={`Réponse ${ci + 1} correcte`}
                    />
                    <span className="text-slate-400" aria-hidden="true">{CHOICE_SHAPES[ci]}</span>
                    <input
                      value={choice}
                      onChange={(e) => updateChoice(question.key, ci, e.target.value)}
                      placeholder={`Réponse ${ci + 1}`}
                      maxLength={255}
                      className="min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-2 py-1.5 outline-none focus:border-indigo-500"
                    />
                  </label>
                ))}
              </div>
            </fieldset>

            {question.error && <p className="mt-2 text-sm font-semibold text-red-600" role="alert">{question.error}</p>}
          </section>
        ))}

        <button
          type="button"
          onClick={() => setQuestions((list) => [...list, emptyQuestion()])}
          className="rounded-2xl border-2 border-dashed border-indigo-300 py-4 font-black text-indigo-600 hover:bg-indigo-50"
        >
          + Ajouter une question
        </button>
      </div>

      {/* Sticky save bar */}
      <div className="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white/95 backdrop-blur">
        <div className="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3">
          <p
            className={`flex-1 text-sm font-semibold ${banner?.type === 'error' ? 'text-red-600' : banner ? 'text-green-700' : 'text-slate-500'}`}
            role="status"
          >
            {banner?.message ?? (hasUnsaved ? 'Modifications non enregistrées' : 'Tout est enregistré')}
          </p>
          <button
            type="button"
            onClick={saveAll}
            disabled={saving || !hasUnsaved}
            className="rounded-xl bg-indigo-600 px-5 py-2.5 font-black text-white shadow hover:bg-indigo-700 disabled:opacity-50"
          >
            {saving ? 'Enregistrement…' : 'Enregistrer'}
          </button>
        </div>
      </div>
    </AdminLayout>
  )
}
