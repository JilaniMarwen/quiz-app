import { QRCodeSVG } from 'qrcode.react'
import { useEffect, useState } from 'react'

/** Public address of the student app (VITE_PUBLIC_URL overrides the current address). */
function studentUrl() {
  return import.meta.env.VITE_PUBLIC_URL || `${window.location.origin}${window.location.pathname}`
}

const isLocalhost = (url) => /\/\/(localhost|127\.0\.0\.1)(:|\/|$)/.test(url)

/** Dashboard card: link + QR code for the students, with a full-screen mode for the projector. */
export default function ShareCard() {
  const url = studentUrl()
  const [fullscreen, setFullscreen] = useState(false)
  const [copied, setCopied] = useState(false)

  useEffect(() => {
    if (!fullscreen) return
    const onKey = (event) => event.key === 'Escape' && setFullscreen(false)
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [fullscreen])

  async function copy() {
    try {
      await navigator.clipboard.writeText(url)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch {
      window.prompt('Copie ce lien :', url)
    }
  }

  return (
    <>
      <section className="mb-6 flex flex-col items-center gap-4 rounded-2xl bg-white p-4 shadow-sm sm:flex-row sm:p-5">
        <QRCodeSVG value={url} size={112} className="shrink-0" />
        <div className="min-w-0 flex-1 text-center sm:text-left">
          <h2 className="font-black">Lien pour les étudiants</h2>
          <p className="mt-1 break-all font-mono text-sm text-indigo-700">{url}</p>
          {isLocalhost(url) && (
            <p className="mt-2 text-xs text-amber-700">
              Adresse locale : les téléphones ne peuvent pas l'ouvrir. Utilise la version en ligne, ou l'adresse IP de ce PC
              sur le même Wi-Fi.
            </p>
          )}
          <div className="mt-3 flex flex-wrap justify-center gap-2 sm:justify-start">
            <button type="button" onClick={() => setFullscreen(true)} className="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-bold text-white hover:bg-indigo-700">
              Afficher en grand
            </button>
            <button type="button" onClick={copy} className="rounded-lg bg-slate-100 px-3 py-2 text-sm font-bold hover:bg-slate-200">
              {copied ? 'Lien copié ✓' : 'Copier le lien'}
            </button>
          </div>
        </div>
      </section>

      {fullscreen && (
        <div
          role="dialog"
          aria-modal="true"
          aria-label="QR code pour les étudiants"
          onClick={() => setFullscreen(false)}
          className="fixed inset-0 z-50 flex cursor-pointer flex-col items-center justify-center gap-6 bg-white p-6 text-center"
        >
          <p className="text-3xl font-black text-slate-800 sm:text-4xl">Scanne pour jouer !</p>
          <QRCodeSVG value={url} size={512} className="h-auto w-[min(80vw,65vh)]" />
          <p className="break-all font-mono text-xl font-bold text-indigo-700 sm:text-2xl">{url}</p>
          <p className="text-sm text-slate-400">Clique ou appuie sur Échap pour fermer</p>
        </div>
      )}
    </>
  )
}
