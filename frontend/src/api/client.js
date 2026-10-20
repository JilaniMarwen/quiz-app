import axios from 'axios'

function storedToken(key) {
  return {
    get: () => localStorage.getItem(key),
    set: (token) => localStorage.setItem(key, token),
    clear: () => localStorage.removeItem(key),
  }
}

export const studentToken = storedToken('quizclasse.studentToken')
export const adminToken = storedToken('quizclasse.adminToken')

const client = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: { Accept: 'application/json' },
})

const isAdminUrl = (url = '') => url.startsWith('/admin')

// Student calls carry X-Student-Token, admin calls a Sanctum bearer token.
client.interceptors.request.use((config) => {
  if (isAdminUrl(config.url)) {
    const token = adminToken.get()
    if (token) config.headers.Authorization = `Bearer ${token}`
  } else {
    const token = studentToken.get()
    if (token) config.headers['X-Student-Token'] = token
  }
  return config
})

const unauthorizedHandlers = { student: null, admin: null }

/** Register what to do when the server rejects the stored token (401). */
export function onUnauthorized(who, handler) {
  unauthorizedHandlers[who] = handler
}

client.interceptors.response.use(
  (response) => response,
  (error) => {
    const url = error.config?.url
    if (error.response?.status === 401 && url !== '/admin/login') {
      unauthorizedHandlers[isAdminUrl(url) ? 'admin' : 'student']?.()
    }
    return Promise.reject(error)
  },
)

/** Human readable (French) message for an API error. */
export function errorMessage(error, fallback = 'Une erreur est survenue. Réessaie.') {
  if (!error.response) return 'Impossible de joindre le serveur. Vérifie ta connexion.'
  const { data, status } = error.response
  if (status >= 500) return 'Erreur du serveur. Réessaie dans un instant.'
  const firstFieldError = data?.errors && Object.values(data.errors)[0]?.[0]
  return firstFieldError || data?.message || fallback
}

export default client
