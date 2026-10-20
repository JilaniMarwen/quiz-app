import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import client, { adminToken, onUnauthorized } from '../api/client.js'

const AdminContext = createContext(null)

/**
 * Professor session: Sanctum bearer token in localStorage.
 * status: 'loading' (checking the token) | 'ready' | 'offline' (server unreachable)
 */
export function AdminProvider({ children }) {
  const [admin, setAdmin] = useState(null)
  const [status, setStatus] = useState(() => (adminToken.get() ? 'loading' : 'ready'))

  const forget = useCallback(() => {
    adminToken.clear()
    setAdmin(null)
    setStatus('ready')
  }, [])

  const verify = useCallback(async () => {
    if (!adminToken.get()) return
    setStatus('loading')
    try {
      const { data } = await client.get('/admin/me')
      setAdmin(data.user)
      setStatus('ready')
    } catch (error) {
      // 401 is handled by forget(); anything else means the server is unreachable.
      if (error.response?.status !== 401) setStatus('offline')
    }
  }, [])

  useEffect(() => {
    onUnauthorized('admin', forget)
    verify()
  }, [forget, verify])

  const login = useCallback(async (username, password) => {
    const { data } = await client.post('/admin/login', { username, password })
    adminToken.set(data.token)
    setAdmin(data.user)
    setStatus('ready')
  }, [])

  const logout = useCallback(async () => {
    try {
      await client.post('/admin/logout')
    } finally {
      forget()
    }
  }, [forget])

  return (
    <AdminContext.Provider value={{ admin, status, login, logout, retry: verify }}>
      {children}
    </AdminContext.Provider>
  )
}

export function useAdmin() {
  return useContext(AdminContext)
}
