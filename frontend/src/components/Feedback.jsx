export function Spinner({ label = 'Chargement…' }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-white/80" role="status">
      <div className="h-10 w-10 animate-spin rounded-full border-4 border-white/30 border-t-white" />
      <span className="text-sm font-semibold">{label}</span>
    </div>
  )
}

export function ErrorBox({ message, onRetry }) {
  return (
    <div className="mx-auto my-8 max-w-md rounded-2xl bg-white p-6 text-center text-slate-800 shadow-xl" role="alert">
      <p className="font-semibold">{message}</p>
      {onRetry && (
        <button
          type="button"
          onClick={onRetry}
          className="mt-4 rounded-xl bg-indigo-600 px-5 py-2 font-bold text-white hover:bg-indigo-700"
        >
          Réessayer
        </button>
      )}
    </div>
  )
}
