import { Link } from 'react-router-dom'
import Layout from '../components/Layout.jsx'

export default function NotFound() {
  return (
    <Layout>
      <div className="mt-20 text-center">
        <p className="text-7xl font-black text-yellow-300">404</p>
        <p className="mt-2 text-lg font-semibold">Cette page n'existe pas.</p>
        <Link to="/" className="mt-6 inline-block rounded-xl bg-white px-5 py-2 font-black text-indigo-700">
          Accueil
        </Link>
      </div>
    </Layout>
  )
}
