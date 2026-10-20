export default function QuizCard({ quiz, onStart, starting }) {
  const done = quiz.status === 'done'
  const inProgress = quiz.status === 'in_progress'
  const closed = !quiz.is_open

  return (
    <article className="flex flex-col gap-4 rounded-2xl bg-white p-5 text-slate-800 shadow-xl sm:flex-row sm:items-center">
      <div className="flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <h2 className="text-lg font-black">{quiz.title}</h2>
          {closed && <span className="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-600">Fermé</span>}
        </div>
        {quiz.description && <p className="mt-1 text-sm text-slate-600">{quiz.description}</p>}
        <p className="mt-1 text-sm text-slate-500">
          {quiz.questions_count} question{quiz.questions_count > 1 ? 's' : ''} · {quiz.time_per_question} s par question
        </p>
      </div>

      {done ? (
        <div className="rounded-xl bg-indigo-50 px-4 py-3 text-center">
          <div className="text-xs font-semibold uppercase text-indigo-500">Score</div>
          <div className="text-2xl font-black text-indigo-700">
            {quiz.score} / {quiz.total}
          </div>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => onStart(quiz)}
          disabled={starting}
          className="rounded-xl bg-indigo-600 px-6 py-3 text-lg font-black text-white shadow hover:bg-indigo-700 disabled:opacity-60"
        >
          {starting ? '…' : inProgress ? 'Reprendre' : 'Commencer'}
        </button>
      )}
    </article>
  )
}
