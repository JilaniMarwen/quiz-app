import { useEffect, useRef, useState } from 'react'

/**
 * Display only: the real timer is enforced by the server (rule 3).
 * `deadline` is a Date.now()-based timestamp computed from the server's remaining_seconds.
 */
export default function Timer({ deadline, duration, onExpire, paused = false }) {
  const [now, setNow] = useState(() => Date.now())
  const expired = useRef(false)
  const onExpireRef = useRef(onExpire)

  useEffect(() => {
    onExpireRef.current = onExpire
  }, [onExpire])

  useEffect(() => {
    expired.current = false
    const tick = () => setNow(Date.now())
    tick()
    const id = setInterval(tick, 100)
    return () => clearInterval(id)
  }, [deadline])

  const remainingMs = Math.min(duration * 1000, Math.max(0, deadline - now))

  useEffect(() => {
    if (remainingMs === 0 && !expired.current && !paused) {
      expired.current = true
      onExpireRef.current?.()
    }
  }, [remainingMs, paused])

  const seconds = Math.ceil(remainingMs / 1000)
  const percent = Math.min(100, (remainingMs / (duration * 1000)) * 100)
  const urgent = seconds <= 5

  return (
    <div className="flex items-center gap-3" aria-label={`${seconds} secondes restantes`}>
      <div className="h-4 flex-1 overflow-hidden rounded-full bg-white/20">
        <div
          className={`h-full rounded-full transition-[width] duration-100 ease-linear ${urgent ? 'bg-red-400' : 'bg-yellow-300'}`}
          style={{ width: `${percent}%` }}
        />
      </div>
      <span
        className={`grid h-12 w-12 shrink-0 place-items-center rounded-full text-xl font-black ${urgent ? 'animate-pulse bg-red-500' : 'bg-white/20'}`}
      >
        {seconds}
      </span>
    </div>
  )
}
