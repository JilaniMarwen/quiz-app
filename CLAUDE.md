# UniversityQuiz

Quiz app for a classroom (Kahoot-like). Full spec and roadmap: see `PLAN.md` — always follow its business rules (section 2).

- `backend/` — Laravel 13 API (Laravel 11 is EOL with unpatched advisories), Sanctum, MySQL (Laragon, db `quiz_app`, user `root`, no password)
- `frontend/` — React + Vite + Tailwind + React Router + Axios
- Windows / Laragon environment. Run backend: `cd backend && php artisan serve --port=8001` (Laragon Apache already uses port 8000). Frontend: `cd frontend && npm run dev`.
- UI text is in French. Mobile-first responsive.
- Never expose `is_correct` in student-facing API responses. Timer is enforced server-side.
- Deployment: frontend on GitHub Pages (static, `HashRouter`, Vite `base: '/quiz-app/'`, API URL from `VITE_API_URL`), backend Laravel + MySQL on Railway. Auth uses bearer tokens (no cookies) because front and API are on different domains. Never commit `.env`.
- Work one roadmap step at a time; add Feature tests for backend rules and run `php artisan test`.
