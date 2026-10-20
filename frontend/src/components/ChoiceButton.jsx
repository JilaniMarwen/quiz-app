// Kahoot-like colour + shape per position.
const STYLES = [
  { color: 'bg-red-500 hover:bg-red-600', shape: '▲' },
  { color: 'bg-blue-500 hover:bg-blue-600', shape: '◆' },
  { color: 'bg-amber-500 hover:bg-amber-600', shape: '●' },
  { color: 'bg-green-600 hover:bg-green-700', shape: '■' },
]

export default function ChoiceButton({ index, text, onClick, disabled, selected, dimmed }) {
  const { color, shape } = STYLES[index % STYLES.length]

  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      className={`flex min-h-20 w-full items-center gap-4 rounded-2xl px-5 py-4 text-left text-lg font-bold text-white shadow-lg transition
        ${color} disabled:cursor-not-allowed
        ${selected ? 'scale-[1.02] ring-4 ring-white' : ''}
        ${dimmed ? 'opacity-40' : ''}
        active:scale-95`}
    >
      <span className="text-2xl" aria-hidden="true">{shape}</span>
      <span className="break-words">{text}</span>
    </button>
  )
}
