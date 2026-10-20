import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import client, { onUnauthorized, studentToken } from '../api/client.js'

const StudentContext = createContext(null)

/**
 * The student token lives in localStorage (rule 1): a page refresh keeps the student signed in.
 * status: 'loading' (checking the token) | 'ready' | 'offline' (server unreachable)
 */
export function StudentProvider({ children }) {
  const [student, setStudent] = useState(null)
  const [status, setStatus] = useState(() => (studentToken.get() ? 'loading' : 'ready'))

  const forget = useCallback(() => {
    studentToken.clear()
    setStudent(null)
    setStatus('ready')
  }, [])

  const verify = useCallback(async () => {
    if (!studentToken.get()) return
    setStatus('loading')
    try {
      const { data } = await client.get('/student/me')
      setStudent(data.student)
      setStatus('ready')
    } catch (error) {
      // 401 is handled by forget(); anything else means the server is unreachable.
      if (error.response?.status !== 401) setStatus('offline')
    }
  }, [])

  useEffect(() => {
    onUnauthorized('student', forget)
    verify()
  }, [forget, verify])

  const register = useCallback(async (name) => {
    const { data } = await client.post('/students', { name })
    studentToken.set(data.token)
    setStudent(data.student)
    setStatus('ready')
  }, [])

  return (
    <StudentContext.Provider value={{ student, status, register, retry: verify }}>
      {children}
    </StudentContext.Provider>
  )
}

export function useStudent() {
  return useContext(StudentContext)
}
