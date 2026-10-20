import { Link } from 'react-router-dom'

/** Full-screen purple background with a small header. */
export default function Layout({ right, children, wide = false }) {
  return (
    <div className="min-h-dvh bg-gradient-to-br from-indigo-700 via-violet-700 to-fuchsia-700 text-white">
      <header className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4">
        <Link to="/" className="text-xl font-black tracking-tight">
          Quiz<span className="text-yellow-300">Classe</span>
        </Link>
        {right}
      </header>
      <main className={`mx-auto px-4 pb-10 ${wide ? 'max-w-5xl' : 'max-w-3xl'}`}>{children}</main>
    </div>
  )
}
