import confetti from 'canvas-confetti'

const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

/** Confetti burst from both sides of the screen. */
export function throwConfetti() {
  if (reducedMotion()) return
  const defaults = { particleCount: 80, spread: 70, startVelocity: 45, zIndex: 50 }
  confetti({ ...defaults, angle: 60, origin: { x: 0, y: 0.8 } })
  confetti({ ...defaults, angle: 120, origin: { x: 1, y: 0.8 } })
}

/** Short jingle generated with Web Audio (no audio file to load). */
export function playChime(success) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)()
    const notes = success ? [523.25, 659.25, 783.99, 1046.5] : [392, 329.63, 261.63]

    notes.forEach((frequency, i) => {
      const oscillator = ctx.createOscillator()
      const gain = ctx.createGain()
      const start = ctx.currentTime + i * 0.13
      oscillator.type = 'triangle'
      oscillator.frequency.value = frequency
      gain.gain.setValueAtTime(0.0001, start)
      gain.gain.exponentialRampToValueAtTime(0.2, start + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.35)
      oscillator.connect(gain).connect(ctx.destination)
      oscillator.start(start)
      oscillator.stop(start + 0.4)
    })

    setTimeout(() => ctx.close(), 2000)
  } catch {
    // Audio not available: silently skip.
  }
}
