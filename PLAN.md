# QuizClasse — Feuille de route

Application web responsive de quiz en classe (alternative à Kahoot), pour la fin de la présentation d'entrepreneuriat.

**Stack :** React (Vite) + Tailwind · Laravel 13 (API) + Sanctum · MySQL · Laragon (Windows)

---

## 1. L'idée (ce que j'ai compris)

### Deux rôles

| Rôle | Comment il entre | Ce qu'il peut faire |
|---|---|---|
| **Étudiant** | Tape son **nom** (unique) — pas de mot de passe | Voir les quiz **ouverts**, passer chaque quiz **une seule fois**, voir son score à côté de chaque quiz déjà fait |
| **Professeur (admin)** | Bouton « Compte professeur » → username + mot de passe | Créer / modifier / supprimer des quiz et des questions, **ouvrir / fermer** un quiz, voir le **classement** des étudiants par quiz |

Il n'existe **qu'un seul** compte professeur, créé par un *seeder* (pas de page d'inscription).

### Parcours étudiant
1. Écran d'accueil : champ « Ton nom » + bouton « Entrer ». En dessous : bouton « Compte professeur ».
2. Si le nom est déjà pris par quelqu'un d'autre → message d'erreur « Ce nom est déjà utilisé ».
3. Liste des quiz ouverts :
   - pas encore fait → bouton **Commencer**
   - déjà fait → affiche **score : 7 / 10** (bouton désactivé)
4. Quiz : **une question à la fois**, 4 réponses, **timer** (ex. 20 s). Temps écoulé = réponse fausse, on passe à la suivante automatiquement.
5. Fin : écran résultat **« 7 / 10 »** → retour à la liste.

### Parcours professeur
1. Connexion (username + mot de passe).
2. Tableau de bord : liste de tous ses quiz avec, pour chacun :
   - interrupteur **Ouvert / Fermé**
   - bouton **Modifier** (titre, temps par question, questions)
   - bouton **Classement** (scores de tous les étudiants, du meilleur au moins bon)
   - bouton **Supprimer**
3. Éditeur de quiz : ajouter autant de questions que voulu, chaque question = texte + **4 réponses** + choix de **la bonne réponse** (bouton radio).

### Scénario en classe
Le prof crée le quiz la veille (**fermé**). En classe il l'**ouvre** → tous les étudiants le voient et le commencent ensemble. À la fin il le **ferme** → le quiz est marqué « Fermé » et n'accepte plus de nouvelles tentatives.

---

## 2. Règles métier (décisions à respecter)

1. **Nom unique** : contrainte `UNIQUE` en base. À la création, le serveur renvoie un **token** stocké dans le `localStorage` → si l'étudiant rafraîchit la page ou revient, il reste connecté. Un autre appareil ne peut pas « voler » un nom déjà pris.
2. **Une seule tentative** par quiz : contrainte `UNIQUE(student_id, quiz_id)` sur `attempts`. Si l'étudiant quitte au milieu, la tentative compte quand même (questions non répondues = fausses). Pas de triche par rafraîchissement : on reprend là où il en était, avec le timer serveur.
3. **Timer côté serveur** : quand une question est envoyée, on enregistre `served_at`. Une réponse reçue après `served_at + temps + 2 s de marge` est comptée fausse. Le timer affiché côté React n'est qu'un affichage.
4. **La bonne réponse n'est jamais envoyée à l'étudiant** par l'API (pas de `is_correct` dans le JSON côté étudiant).
5. **Score** = nombre de bonnes réponses / nombre total de questions du quiz.
6. **Classement** : trié par score décroissant, puis par **temps total** croissant (départage des ex-aequo).
7. **Quiz fermé** : invisible (ou grisé « Fermé ») pour les étudiants, impossible de **commencer**. Une tentative déjà en cours au moment de la fermeture peut se terminer.
8. **Quiz ouvrable seulement s'il a ≥ 1 question** et que chaque question a exactement 4 réponses dont 1 correcte.
9. **Modification protégée** : si un quiz a déjà des tentatives, l'admin est averti (modifier les questions fausserait les scores). Option : bouton « Réinitialiser les résultats ».

---

## 3. Base de données (MySQL)

```
users            (admin)        id, username UNIQUE, password, timestamps
students                        id, name UNIQUE, token (hashé), timestamps
quizzes                         id, title, description NULL, time_per_question INT default 20,
                                is_open BOOL default false, timestamps
questions                       id, quiz_id FK→quizzes (cascade), text, position INT, timestamps
choices                         id, question_id FK→questions (cascade), text, is_correct BOOL, position INT
attempts                        id, student_id FK, quiz_id FK, started_at, finished_at NULL,
                                score INT default 0, total INT,
                                UNIQUE(student_id, quiz_id)
attempt_answers                 id, attempt_id FK (cascade), question_id FK, choice_id NULL FK,
                                is_correct BOOL default false, served_at, answered_at NULL,
                                UNIQUE(attempt_id, question_id)
```

Relations Eloquent : `Quiz hasMany Question`, `Question hasMany Choice`, `Student hasMany Attempt`, `Attempt hasMany AttemptAnswer`, `Quiz hasMany Attempt`.

---

## 4. API Laravel

### Étudiant (middleware `student` : vérifie le header `X-Student-Token`)

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/api/students` | `{name}` → crée l'étudiant, renvoie `{student, token}` (422 si nom pris) |
| GET | `/api/student/me` | vérifier le token au rechargement |
| GET | `/api/student/quizzes` | quiz ouverts + statut pour cet étudiant (`not_started` / `in_progress` / `done` + score) |
| POST | `/api/student/quizzes/{quiz}/start` | crée la tentative (409 si déjà existante → renvoie la tentative) |
| GET | `/api/student/attempts/{attempt}/current` | question courante **sans** bonne réponse + `remaining_seconds`, index `3/10` |
| POST | `/api/student/attempts/{attempt}/answer` | `{question_id, choice_id \| null}` → enregistre, renvoie la question suivante ou `finished` |
| GET | `/api/student/attempts/{attempt}/result` | `{score, total}` |

### Professeur (Sanctum, `auth:sanctum`)

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/api/admin/login` / `/api/admin/logout` | connexion par token Sanctum |
| GET/POST | `/api/admin/quizzes` | liste (avec nb questions, nb participants) / créer |
| GET/PUT/DELETE | `/api/admin/quizzes/{quiz}` | détail avec questions+choix / modifier / supprimer |
| PATCH | `/api/admin/quizzes/{quiz}/toggle` | ouvrir ↔ fermer (refuse si quiz invalide) |
| POST | `/api/admin/quizzes/{quiz}/questions` | ajouter une question + 4 choix |
| PUT/DELETE | `/api/admin/questions/{question}` | modifier / supprimer une question |
| GET | `/api/admin/quizzes/{quiz}/leaderboard` | classement trié (rang, nom, score/total, durée) |
| DELETE | `/api/admin/quizzes/{quiz}/attempts` | (option) réinitialiser les résultats |

Validation via **Form Requests** : exactement 4 choix, exactement 1 `is_correct = true`, textes non vides.

---

## 5. Frontend React

**Librairies :** Vite, React Router (**`HashRouter`**, obligatoire pour GitHub Pages → URLs du type `/#/quizzes`), Axios (URL de base = `import.meta.env.VITE_API_URL`), Tailwind CSS. (Option : `react-hot-toast` pour les messages.)

### Pages / routes

| Route | Page |
|---|---|
| `/` | Accueil : champ nom + bouton « Compte professeur » |
| `/quizzes` | Liste des quiz pour l'étudiant (cartes avec score ou bouton Commencer) |
| `/play/:attemptId` | Écran de jeu : question, 4 boutons colorés (style Kahoot), barre de timer, progression `3/10` |
| `/result/:attemptId` | Score final, gros et lisible |
| `/prof/login` | Connexion professeur |
| `/prof` | Tableau de bord admin |
| `/prof/quizzes/new` et `/prof/quizzes/:id/edit` | Éditeur de quiz + questions |
| `/prof/quizzes/:id/leaderboard` | Classement (podium top 3 + tableau) — rafraîchi toutes les 3 s pendant le quiz |

### Organisation
```
frontend/src/
  api/client.js          axios + intercepteurs (token étudiant / token admin)
  context/StudentContext.jsx, AdminContext.jsx
  components/            Timer, ChoiceButton, QuizCard, ProtectedRoute, Layout
  pages/student/...      Home, QuizList, Play, Result
  pages/admin/...        Login, Dashboard, QuizEditor, Leaderboard
```

**Responsive** : pensé mobile d'abord (les étudiants jouent sur téléphone), boutons de réponse en grille 1 colonne sur mobile, 2×2 sur tablette/PC.

---

## 6. Structure du projet

```
C:\laragon\www\quiz-app\
  backend\     Laravel 13 (API)
  frontend\    React + Vite
  .github\workflows\deploy-frontend.yml   déploiement auto sur GitHub Pages
  PLAN.md      ce fichier
  CLAUDE.md    instructions pour Claude Code
```
Un seul repo GitHub (`quiz-app`) contient les deux parties. Frontend → **GitHub Pages**, backend → **Railway** (ou Render).

---

## 7. Feuille de route (étapes à donner à Claude Code)

Faire **une étape à la fois**, tester, puis passer à la suivante. Chaque étape contient un prompt prêt à copier.

### Étape 0 — Mise en place (≈ 30 min)
- Laragon démarré (Apache/Nginx + MySQL). Créer la base `quiz_app` (HeidiSQL ou `mysql -u root -e "CREATE DATABASE quiz_app"`).
- > *Prompt :* « Lis PLAN.md. Crée le projet Laravel (13) dans `backend/` avec Sanctum (`php artisan install:api`), configure `.env` pour MySQL Laragon (base `quiz_app`, user `root`, sans mot de passe) et configure CORS pour `http://localhost:5173`. Crée le projet React Vite dans `frontend/` avec Tailwind, React Router et Axios. »
- ✅ Test : `php artisan serve` et `npm run dev` démarrent tous les deux.

### Étape 1 — Base de données (≈ 1 h)
- > *Prompt :* « Selon la section 3 de PLAN.md, crée les migrations, modèles Eloquent avec relations, et un seeder qui crée le compte admin (username/password lus depuis `.env` : `ADMIN_USERNAME`, `ADMIN_PASSWORD`) et un quiz de démonstration de 5 questions sur l'entrepreneuriat. »
- ✅ Test : `php artisan migrate:fresh --seed` puis vérifier les tables dans HeidiSQL.

### Étape 2 — API professeur (≈ 2 h)
- > *Prompt :* « Implémente toutes les routes admin de la section 4 de PLAN.md avec Sanctum, Form Requests (4 choix, 1 seul correct) et la règle 8 (pas d'ouverture d'un quiz invalide). Ajoute des tests Feature. »
- ✅ Test : `php artisan test` + essai avec Postman/Thunder Client.

### Étape 3 — API étudiant (≈ 2–3 h) — *le cœur du projet*
- > *Prompt :* « Implémente les routes étudiant de la section 4 en respectant strictement les règles 1 à 7 de la section 2 (nom unique + token, une seule tentative, timer côté serveur avec `served_at`, jamais de `is_correct` envoyé à l'étudiant). Ajoute des tests Feature pour : nom déjà pris, 2e tentative refusée, réponse hors délai comptée fausse, quiz fermé non démarrable. »
- ✅ Test : `php artisan test`.

### Étape 4 — Front étudiant (≈ 3 h)
- > *Prompt :* « Crée les pages étudiant de la section 5 (Home, QuizList, Play, Result). Le token étudiant est dans le localStorage. L'écran Play affiche une barre de timer qui se base sur `remaining_seconds` du serveur et envoie automatiquement `choice_id: null` quand le temps est écoulé. Style moderne type Kahoot, mobile d'abord. »
- ✅ Test : jouer un quiz complet, rafraîchir au milieu, vérifier que le score s'affiche sur la carte et que le quiz n'est plus rejouable.

### Étape 5 — Front professeur (≈ 3 h)
- > *Prompt :* « Crée les pages professeur de la section 5 : Login, Dashboard (interrupteur ouvert/fermé, boutons Modifier/Classement/Supprimer), QuizEditor (ajout dynamique de questions, 4 champs réponse + radio pour la bonne), Leaderboard (podium top 3 + tableau, rafraîchissement toutes les 3 s). Routes protégées. »
- ✅ Test : créer un quiz de A à Z, l'ouvrir, le jouer depuis un autre navigateur (fenêtre privée), voir le classement se mettre à jour.

### Étape 6 — Finitions (≈ 2 h)
- Messages d'erreur clairs, états de chargement, page 404.
- Vérifier le responsive (outil mobile du navigateur, vrai téléphone).
- Petits plus si le temps le permet : son / animation à la fin, couleur verte/rouge du score, **QR code** de l'adresse de l'app affiché sur le dashboard prof.

### Étape 7 — Mise en ligne : GitHub + lien pour les étudiants

> ⚠️ **GitHub Pages n'héberge que des fichiers statiques** (HTML/JS/CSS). Il peut héberger le **frontend React**, mais **pas Laravel ni MySQL**. Le backend doit donc être hébergé ailleurs.

```
Téléphone étudiant
   │  https://<ton-user>.github.io/quiz-app/      ← lien partagé (QR code)
   ▼
Frontend React  (GitHub Pages, déployé automatiquement par GitHub Actions)
   │  appels API  https://quiz-app-api.up.railway.app/api/...
   ▼
Backend Laravel + MySQL  (Railway, Render… connecté au même repo GitHub)
```

**7.1 — Préparer le code (prompt Claude Code)**
> « Prépare le projet pour le déploiement selon l'étape 7 de PLAN.md :
> - frontend : `base: '/quiz-app/'` dans `vite.config.js`, utiliser **`HashRouter`** (évite les 404 de GitHub Pages au rafraîchissement), URL de l'API dans `VITE_API_URL` ;
> - créer `.github/workflows/deploy-frontend.yml` qui build `frontend/` et déploie sur GitHub Pages à chaque push sur `main` (avec `VITE_API_URL` lu depuis les *repository variables*) ;
> - backend : CORS autorisant `https://<user>.github.io` via la variable `FRONTEND_URL`, un `Dockerfile` (ou config Nixpacks) pour l'hébergeur, la commande de démarrage qui lance `php artisan migrate --force` ;
> - `.gitignore` corrects (`vendor/`, `node_modules/`, `.env`, `dist/`) et `.env.example` à jour. »

**7.2 — Pousser sur GitHub**
1. Créer un repo **`quiz-app`** sur github.com (public, obligatoire pour GitHub Pages gratuit).
2. Dans `C:\laragon\www\quiz-app` :
   ```
   git init
   git add .
   git commit -m "QuizClasse"
   git branch -M main
   git remote add origin https://github.com/<ton-user>/quiz-app.git
   git push -u origin main
   ```
   ⚠️ Vérifier que **`.env` n'est jamais poussé** (mot de passe admin, clés).

**7.3 — Héberger le backend**
- **Railway** (recommandé, le plus simple) : *New Project → Deploy from GitHub repo* → root directory `backend` + ajouter un service **MySQL**. Renseigner les variables : `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `DB_*` (fournies par Railway), `ADMIN_USERNAME`, `ADMIN_PASSWORD`, `FRONTEND_URL`. Le compte prof est (re)créé automatiquement à chaque démarrage depuis `ADMIN_USERNAME` / `ADMIN_PASSWORD` (voir `backend/Dockerfile`).
- Alternative : **Render** (service Docker) + une base MySQL externe (ex. Aiven). ⚠️ Le plan gratuit de Render **s'endort** après inactivité → ouvrir l'app 2 min avant le cours.
- Vérifier les tarifs actuels : ces offres changent souvent (Railway donne en général un crédit d'essai suffisant pour un projet de cours).

**7.4 — Activer GitHub Pages**
1. Repo → *Settings → Pages* → Source : **GitHub Actions**.
2. *Settings → Secrets and variables → Actions → Variables* → `VITE_API_URL = https://<url-railway>/api`.
3. Pousser sur `main` → l'onglet *Actions* déploie → l'app est en ligne sur **`https://<ton-user>.github.io/quiz-app/`**.

**7.5 — Le jour J**
- Générer un **QR code** du lien et le mettre sur la dernière slide de la présentation.
- Le prof se connecte, **ouvre** le quiz → les étudiants scannent et jouent.
- **Répétition générale** la veille avec 2–3 amis sur leurs téléphones (4G et Wi-Fi).
- Plan B : garder la version locale (Laragon + `php artisan serve --host=0.0.0.0` + partage de connexion du téléphone).

---

## 8. Checklist finale
- [ ] Nom déjà pris → refusé
- [ ] Rafraîchir la page ne déconnecte pas l'étudiant
- [ ] Impossible de refaire un quiz (même en rafraîchissant / via l'API)
- [ ] Temps écoulé → question suivante + compté faux
- [ ] La bonne réponse n'apparaît pas dans l'onglet Réseau du navigateur
- [ ] Quiz fermé → non visible / non démarrable
- [ ] Classement trié correctement, ex-aequo départagés par le temps
- [ ] Fonctionne sur téléphone
- [ ] Mot de passe admin changé (pas celui par défaut)
