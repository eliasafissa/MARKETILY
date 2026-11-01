import { FormEvent, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { authApi } from '@/api/client';

export default function ResetPassword() {
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const [email, setEmail] = useState(params.get('email') ?? '');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const token = params.get('token') ?? '';
  async function submit(e: FormEvent) {
    e.preventDefault(); setBusy(true); setError('');
    try {
      await authApi.resetPassword({ email, token, password, password_confirmation: confirmation });
      navigate('/login', { replace: true });
    } catch (e: any) {
      setError(e.response?.data?.message ?? 'Reset failed. Please request a new link.');
    } finally { setBusy(false); }
  }
  return <main className="min-h-[65vh] flex items-center justify-center p-6">
    <form onSubmit={submit} className="card-pad w-full max-w-md space-y-5">
      <h1 className="text-h2 text-gray-900 dark:text-ink-900">Reset password / تغيير كلمة المرور</h1>
      {!token && <p role="alert">Missing reset token. / رابط الاستعادة غير صالح.</p>}
      <input className="input" type="email" required autoComplete="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="Email" />
      <input className="input" type="password" required minLength={10} autoComplete="new-password" value={password} onChange={e => setPassword(e.target.value)} placeholder="New password / كلمة المرور الجديدة" />
      <input className="input" type="password" required minLength={10} autoComplete="new-password" value={confirmation} onChange={e => setConfirmation(e.target.value)} placeholder="Confirm password / تأكيد كلمة المرور" />
      <button className="btn-accent w-full" type="submit" disabled={!token || busy}>{busy ? 'Please wait...' : 'Reset password'}</button>
      {error && <p role="alert" className="text-red-500">{error}</p>}
      <Link to="/forgot-password" className="block text-green-500 underline">Get a new link</Link>
    </form>
  </main>;
}
