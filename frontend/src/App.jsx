import { Route, Routes } from 'react-router-dom'
import AdminRoute from './components/AdminRoute.jsx'
import StudentRoute from './components/StudentRoute.jsx'
import { AdminProvider } from './context/AdminContext.jsx'
import { StudentProvider } from './context/StudentContext.jsx'
import NotFound from './pages/NotFound.jsx'
import Dashboard from './pages/admin/Dashboard.jsx'
import Leaderboard from './pages/admin/Leaderboard.jsx'
import AdminLogin from './pages/admin/Login.jsx'
import QuizEditor from './pages/admin/QuizEditor.jsx'
import Home from './pages/student/Home.jsx'
import Play from './pages/student/Play.jsx'
import QuizList from './pages/student/QuizList.jsx'
import Result from './pages/student/Result.jsx'

export default function App() {
  return (
    <StudentProvider>
      <AdminProvider>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route element={<StudentRoute />}>
            <Route path="/quizzes" element={<QuizList />} />
            <Route path="/play/:attemptId" element={<Play />} />
            <Route path="/result/:attemptId" element={<Result />} />
          </Route>

          <Route path="/prof/login" element={<AdminLogin />} />
          <Route element={<AdminRoute />}>
            <Route path="/prof" element={<Dashboard />} />
            <Route path="/prof/quizzes/new" element={<QuizEditor />} />
            <Route path="/prof/quizzes/:id/edit" element={<QuizEditor />} />
            <Route path="/prof/quizzes/:id/leaderboard" element={<Leaderboard />} />
          </Route>

          <Route path="*" element={<NotFound />} />
        </Routes>
      </AdminProvider>
    </StudentProvider>
  )
}
