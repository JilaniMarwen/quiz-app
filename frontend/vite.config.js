import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ command }) => ({
  // GitHub Pages serves the app under /<repo>/ (the workflow passes VITE_BASE); dev stays at /.
  base: command === 'build' ? process.env.VITE_BASE || '/quiz-app/' : '/',
  plugins: [react(), tailwindcss()],
}))
