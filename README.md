# UniversityQuiz

Quiz en classe façon Kahoot : les étudiants entrent leur nom et jouent sur leur téléphone, le professeur crée les quiz, les ouvre / ferme et suit le classement en direct.

- `backend/` — API Laravel 13 + Sanctum + MySQL
- `frontend/` — React (Vite) + Tailwind
- Spécification et règles métier : [PLAN.md](PLAN.md)

## En local (Laragon)

```bash
# Backend — http://localhost:8001 (le port 8000 est pris par Apache de Laragon)
cd backend
composer install
cp .env.example .env              # puis renseigner ADMIN_PASSWORD
php artisan key:generate
php artisan migrate --seed        # compte prof + quiz de démonstration
php artisan serve --port=8001

# Frontend — http://localhost:5173
cd frontend
npm install
cp .env.example .env
npm run dev
```

Tests : `cd backend && php artisan test`

Pour jouer depuis des téléphones sur le même Wi-Fi : `npm run dev -- --host`, `php artisan serve --host=0.0.0.0 --port=8001`, puis mettre l'IP du PC dans `VITE_API_URL` et `FRONTEND_URL`.

## Mise en ligne

### 1. Backend sur Railway
1. *New Project → Deploy from GitHub repo* → choisir ce repo, **Root Directory : `backend`** (le `Dockerfile` est détecté).
2. Ajouter un service **MySQL** au projet.
3. Variables du service backend :

| Variable | Valeur |
|---|---|
| `APP_KEY` | résultat de `php artisan key:generate --show` |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | l'URL publique Railway du backend |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` `DB_PORT` `DB_DATABASE` `DB_USERNAME` `DB_PASSWORD` | références vers le service MySQL (`${{MySQL.MYSQLHOST}}`, etc.) |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | identifiants du prof — **mot de passe solide** |
| `FRONTEND_URL` | `https://<ton-user>.github.io` |

4. *Settings → Networking → Generate Domain*. Au démarrage, le conteneur lance les migrations et crée le compte prof.
5. Vérifier : `https://<backend>/up` répond 200.

### 2. Frontend sur GitHub Pages
1. Repo → *Settings → Pages* → Source : **GitHub Actions**.
2. *Settings → Secrets and variables → Actions → Variables* :
   - `VITE_API_URL` = `https://<backend>/api`
   - `VITE_PUBLIC_URL` (optionnel) = `https://<ton-user>.github.io/<repo>/` — adresse du QR code
3. Pousser sur `main` → l'onglet *Actions* déploie sur `https://<ton-user>.github.io/<repo>/`.

`.env` n'est jamais commité (voir `.gitignore`).
